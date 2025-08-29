<?php

namespace App\Controller;

use App\Entity\Blencours;
use App\Form\BlencoursType;
use App\Form\BlencoursFilterType;
use App\Repository\BlencoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\Persistence\ManagerRegistry;

#[Route('/blencours')]
class BlencoursController extends AbstractController
{
    /**
     * Liste des BL en cours
     */
    #[Route('/', name: 'app_blencours_index', methods: ['GET'])]
    public function index(Request $request, BlencoursRepository $blencoursRepository): Response
    {
        $filters = [];
        $sortField = $request->query->get('sort', 'adddate');
        $sortDirection = $request->query->get('direction', 'DESC');
        
        // Formulaire de filtre
        $filterForm = $this->createForm(BlencoursFilterType::class);
        $filterForm->handleRequest($request);
        
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $filters = $filterForm->getData();
        }
        
        // Pagination
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        
        $result = $blencoursRepository->findAllPaginated($page, $limit, $filters, $sortField, $sortDirection);
        $blencours = $result['items'];
        $totalItems = $result['totalItems'];
        
        // Statistiques
        $stats = $blencoursRepository->getStats();
        
        return $this->render('blencours/index.html.twig', [
            'blencours' => $blencours,
            'filterForm' => $filterForm,
            'stats' => $stats,
            'currentPage' => $page,
            'totalPages' => ceil($totalItems / $limit),
            'totalItems' => $totalItems,
            'sortField' => $sortField,
            'sortDirection' => $sortDirection
        ]);
    }

    /**
     * Nouveau BL - Version améliorée avec filtrage des BL non-existants
     */
    #[Route('/new', name: 'app_blencours_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        ManagerRegistry $doctrine,
        BlencoursRepository $blencoursRepository
    ): Response
    {
        $blencour = new Blencours();
        
        // Définir la valeur par défaut du statut à "En cours"
        $blencour->setStatut('En cours');
        
        $form = $this->createForm(BlencoursType::class, $blencour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si le numéro de BL existe déjà
            $numBl = $blencour->getNumBl();
            $existingBl = $blencoursRepository->findOneBy(['numBl' => $numBl]);
            
            if ($existingBl) {
                $this->addFlash('error', 'Le numéro de BL "' . $numBl . '" existe déjà. Veuillez utiliser un autre numéro.');
            } else {
                // Date d'ajout
                $blencour->setAdddate(new \DateTimeImmutable());
                // Enregistrer le BL
                $blencoursRepository->save($blencour);
                
                $this->addFlash('success', 'Le BL a été créé avec succès.');
                return $this->redirectToRoute('app_blencours_index');
            }
        }

        // Récupérer les BL disponibles (non-existants dans Blencours) avec optimisation
        $availableBlList = $this->getAvailableBlForToday($entityManager);
        $stats = $this->getBlStatsForToday($entityManager);
        
        // Calculer les statistiques CI9 vs autres
        $ci9Count = 0;
        $otherCount = 0;
        foreach ($availableBlList as $bl) {
            $codeClient = $bl['Code_Client'] ?? '';
            if (strpos($codeClient, 'CI9') === 0) {
                $ci9Count++;
            } else {
                $otherCount++;
            }
        }
        
        return $this->render('blencours/new.html.twig', [
            'blencour' => $blencour,
            'form' => $form,
            'todayBlList' => $availableBlList,
            'totalBlToday' => $stats['total'],
            'availableBlCount' => $stats['available'],
            'existingBlCount' => $stats['existing'],
            'ci9Count' => $ci9Count,
            'otherCount' => $otherCount,
        ]);
    }

    /**
     * Afficher la page de confirmation pour effacer toutes les données
     */
    #[Route('/clear-confirm', name: 'app_blencours_clear_confirm', methods: ['GET'])]
    public function clearConfirm(BlencoursRepository $blencoursRepository): Response
    {
        // Récupérer le nombre total d'enregistrements
        $totalCount = $blencoursRepository->count([]);
        
        return $this->render('blencours/clear_confirm.html.twig', [
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * Effacer toutes les données de l'entité Blencours
     */
    #[Route('/clear-all', name: 'app_blencours_clear_all', methods: ['POST'])]
    public function clearAll(Request $request, BlencoursRepository $blencoursRepository): Response
    {
        // Vérification du token CSRF pour la sécurité
        if (!$this->isCsrfTokenValid('clear_all_blencours', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_blencours_index');
        }

        try {
            // Compter les enregistrements avant suppression
            $countBeforeDeletion = $blencoursRepository->count([]);
            
            if ($countBeforeDeletion > 0) {
                // Effacer toutes les données
                $blencoursRepository->clearAll();
                $this->addFlash('success', "Tous les BL ont été supprimés avec succès ! ($countBeforeDeletion enregistrements supprimés)");
            } else {
                $this->addFlash('info', 'Aucun BL à supprimer, la liste est déjà vide.');
            }
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression : ' . $e->getMessage());
        }

        // Redirection vers la page index des BL en cours
        return $this->redirectToRoute('app_blencours_index');
    }

    /**
     * Effacer uniquement les BL avec un statut spécifique
     */
    #[Route('/clear-by-status', name: 'app_blencours_clear_by_status', methods: ['POST'])]
    public function clearByStatus(Request $request, BlencoursRepository $blencoursRepository): Response
    {
        if (!$this->isCsrfTokenValid('clear_by_status', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_blencours_index');
        }

        $status = $request->request->get('status');
        $validStatuses = ['En attente', 'En cours', 'Traité'];
        
        if (!in_array($status, $validStatuses)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_blencours_index');
        }

        try {
            $deletedCount = $blencoursRepository->deleteByStatus($status);
            
            if ($deletedCount > 0) {
                $this->addFlash('success', "Tous les BL avec le statut \"$status\" ont été supprimés ! ($deletedCount enregistrements supprimés)");
            } else {
                $this->addFlash('info', "Aucun BL avec le statut \"$status\" trouvé à supprimer.");
            }
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression : ' . $e->getMessage());
        }

        // Redirection vers la page index des BL en cours
        return $this->redirectToRoute('app_blencours_index');
    }

    /**
     * Récupération des données d'un BL pour AJAX
     */
    #[Route('/get-bl-data/{blNumber}', name: 'app_blencours_get_bl_data', methods: ['GET'])]
    public function getBlData(string $blNumber, ManagerRegistry $doctrine): JsonResponse
    {
        $suividupreparationdujourRepository = $doctrine->getRepository('App\Entity\Suividupreparationdujour');
        $blData = $suividupreparationdujourRepository->findBy(['No_Bl' => $blNumber]);
        
        $formattedData = [];
        
        if (!empty($blData)) {
            foreach ($blData as $bl) {
                $formattedData[] = [
                    'No_Bl' => $bl->getNoBl(),
                    'Client' => $bl->getClient(),
                    'Code_Client' => $bl->getCodeClient(),
                    'Transporteur' => $bl->getTransporteur(),
                    'No_Cmd' => $bl->getNoCmd(),
                    'Date_liv' => $bl->getDateLiv() ? $bl->getDateLiv()->format('Y-m-d') : null,
                    'Statut_Cde' => $bl->getStatutCde(),
                    'Preparateur' => $bl->getPreparateur()
                ];
            }
        }
        
        return $this->json([
            'success' => true,
            'data' => $formattedData,
        ]);
    }

    /**
     * Vérifier si un BL existe
     */
    #[Route('/check-bl-exists/{numBl}', name: 'app_blencours_check_exists', methods: ['GET'])]
    public function checkBlExists(string $numBl, BlencoursRepository $blencoursRepository): JsonResponse
    {
        $existingBl = $blencoursRepository->findOneBy(['numBl' => $numBl]);
        
        return $this->json([
            'exists' => $existingBl !== null,
        ]);
    }

    /**
     * Recherche des BL (pour l'autocomplétion)
     */
    #[Route('/search/autocomplete', name: 'app_blencours_search_autocomplete', methods: ['GET'])]
    public function searchAutocomplete(Request $request, BlencoursRepository $blencoursRepository): JsonResponse
    {
        $term = $request->query->get('term', '');
        
        if (empty($term) || strlen($term) < 2) {
            return $this->json([]);
        }
        
        $results = $blencoursRepository->searchByNumBLOrStatut($term);
        
        $formattedResults = [];
        foreach ($results as $blencour) {
            $formattedResults[] = [
                'id' => $blencour->getId(),
                'numBl' => $blencour->getNumBl(),
                'statut' => $blencour->getStatut(),
                'date' => $blencour->getAdddate() ? $blencour->getAdddate()->format('d/m/Y H:i') : ''
            ];
        }
        
        return $this->json($formattedResults);
    }

    // ==========================================
    // TOUTES LES ROUTES AVEC {id} DOIVENT ÊTRE À LA FIN !
    // ==========================================

    /**
     * Détail d'un BL
     */
    #[Route('/{id}', name: 'app_blencours_show', methods: ['GET'])]
    public function show(Blencours $blencour): Response
    {
        return $this->render('blencours/show.html.twig', [
            'blencour' => $blencour,
        ]);
    }

    /**
     * Modifier un BL
     */
    #[Route('/{id}/edit', name: 'app_blencours_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Blencours $blencour, BlencoursRepository $blencoursRepository): Response
    {
        $originalNumBl = $blencour->getNumBl();
        $form = $this->createForm(BlencoursType::class, $blencour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newNumBl = $blencour->getNumBl();
            
            // Vérifier uniquement si le numéro de BL a changé
            if ($newNumBl !== $originalNumBl) {
                $existingBl = $blencoursRepository->findOneBy(['numBl' => $newNumBl]);
                
                if ($existingBl && $existingBl->getId() !== $blencour->getId()) {
                    $this->addFlash('error', 'Le numéro de BL "' . $newNumBl . '" existe déjà. Veuillez utiliser un autre numéro.');
                    
                    return $this->render('blencours/edit.html.twig', [
                        'blencour' => $blencour,
                        'form' => $form,
                    ]);
                }
            }
            
            $blencoursRepository->save($blencour);
            $this->addFlash('success', 'Le BL a été modifié avec succès.');
            
            return $this->redirectToRoute('app_blencours_index');
        }

        return $this->render('blencours/edit.html.twig', [
            'blencour' => $blencour,
            'form' => $form,
        ]);
    }

    /**
     * Supprimer un BL
     */
    #[Route('/{id}', name: 'app_blencours_delete', methods: ['POST'])]
    public function delete(Request $request, Blencours $blencour, BlencoursRepository $blencoursRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$blencour->getId(), $request->request->get('_token'))) {
            $blencoursRepository->remove($blencour);
            $this->addFlash('success', 'Le BL a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_blencours_index');
    }

    /**
     * Marquer un BL comme "Traité"
     */
    #[Route('/{id}/complete', name: 'app_blencours_complete', methods: ['POST'])]
    public function complete(Request $request, Blencours $blencour, BlencoursRepository $blencoursRepository): Response
    {
        if ($this->isCsrfTokenValid('complete'.$blencour->getId(), $request->request->get('_token'))) {
            $blencoursRepository->markAsCompleted($blencour);
            $this->addFlash('success', 'Le BL a été marqué comme "Traité".');
        }

        return $this->redirectToRoute('app_blencours_index');
    }

    /**
     * Mise à jour rapide du statut en AJAX
     */
    #[Route('/{id}/update-status', name: 'app_blencours_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, Blencours $blencour, BlencoursRepository $blencoursRepository): JsonResponse
    {
        $statusMapping = [
            'pending' => 'En attente',
            'processing' => 'En cours',
            'completed' => 'Traité'
        ];
        
        $newStatus = $request->request->get('status');
        
        if (isset($statusMapping[$newStatus])) {
            $blencour->setStatut($statusMapping[$newStatus]);
            $blencoursRepository->save($blencour);
            
            return $this->json([
                'success' => true,
                'status' => $statusMapping[$newStatus]
            ]);
        }
        
        return $this->json([
            'success' => false,
            'message' => 'Statut invalide'
        ], 400);
    }

    // ==========================================
    // MÉTHODES PRIVÉES
    // ==========================================

    /**
     * Récupère les BL du jour qui n'existent pas encore dans Blencours
     * Triés avec les CI9 en premier
     * 
     * @param EntityManagerInterface $entityManager
     * @return array
     */
    private function getAvailableBlForToday(EntityManagerInterface $entityManager): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');
        
        // Requête optimisée avec LEFT JOIN pour exclure les BL déjà existants
        $query = $entityManager->createQuery('
            SELECT DISTINCT s.No_Bl, s.Client, s.Code_Client, s.Transporteur, s.Statut_Cde, s.No_Cmd, s.Date_liv, s.Preparateur
            FROM App\Entity\Suividupreparationdujour s
            LEFT JOIN App\Entity\Blencours b WITH s.No_Bl = b.numBl
            WHERE s.updatedAt >= :today 
            AND s.updatedAt < :tomorrow 
            AND s.No_Bl IS NOT NULL
            AND s.No_Bl != \'\'
            AND b.numBl IS NULL
            ORDER BY 
                CASE WHEN s.Code_Client LIKE :ci9Pattern THEN 0 ELSE 1 END,
                s.No_Bl ASC
        ');
        
        $query->setParameter('today', $today);
        $query->setParameter('tomorrow', $tomorrow);
        $query->setParameter('ci9Pattern', 'CI9%');
        
        return $query->getResult();
    }

    /**
     * Récupère les statistiques des BL du jour
     * 
     * @param EntityManagerInterface $entityManager
     * @return array
     */
    private function getBlStatsForToday(EntityManagerInterface $entityManager): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');
        
        // Total des BL du jour
        $totalQuery = $entityManager->createQuery('
            SELECT COUNT(DISTINCT s.No_Bl) as total
            FROM App\Entity\Suividupreparationdujour s
            WHERE s.updatedAt >= :today 
            AND s.updatedAt < :tomorrow 
            AND s.No_Bl IS NOT NULL
            AND s.No_Bl != \'\'
        ');
        $totalQuery->setParameter('today', $today);
        $totalQuery->setParameter('tomorrow', $tomorrow);
        $totalBlToday = $totalQuery->getSingleScalarResult();
        
        // BL déjà créés dans Blencours
        $existingQuery = $entityManager->createQuery('
            SELECT COUNT(DISTINCT s.No_Bl) as existing
            FROM App\Entity\Suividupreparationdujour s
            INNER JOIN App\Entity\Blencours b WITH s.No_Bl = b.numBl
            WHERE s.updatedAt >= :today 
            AND s.updatedAt < :tomorrow 
            AND s.No_Bl IS NOT NULL
            AND s.No_Bl != \'\'
        ');
        $existingQuery->setParameter('today', $today);
        $existingQuery->setParameter('tomorrow', $tomorrow);
        $existingBlCount = $existingQuery->getSingleScalarResult();
        
        $availableBlCount = $totalBlToday - $existingBlCount;
        
        return [
            'total' => (int) $totalBlToday,
            'existing' => (int) $existingBlCount,
            'available' => (int) $availableBlCount,
        ];
    }
}