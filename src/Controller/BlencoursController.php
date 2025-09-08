<?php

namespace App\Controller;

use App\Entity\Blencours;
use App\Form\BlencoursType;
use App\Form\BlencoursFilterType;
use App\Repository\BlencoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_ADMIN')]
#[Route('/blencours')]
class BlencoursController extends AbstractController
{
    #[Route('/', name: 'app_blencours_index', methods: ['GET'])]
    public function index(Request $request, BlencoursRepository $blencoursRepository): Response
    {
        $filters = [];
        $sortField = $request->query->get('sort', 'adddate');
        $sortDirection = $request->query->get('direction', 'DESC');

        $filterForm = $this->createForm(BlencoursFilterType::class);
        $filterForm->handleRequest($request);

        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $filters = $filterForm->getData();
        }

        $page  = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $result     = $blencoursRepository->findAllPaginated($page, $limit, $filters, $sortField, $sortDirection);
        $blencours  = $result['items'];
        $totalItems = $result['totalItems'];
        $stats      = $blencoursRepository->getStats();

        return $this->render('blencours/index.html.twig', [
            'blencours'     => $blencours,
            'filterForm'    => $filterForm,
            'stats'         => $stats,
            'currentPage'   => $page,
            'totalPages'    => (int) ceil($totalItems / $limit),
            'totalItems'    => $totalItems,
            'sortField'     => $sortField,
            'sortDirection' => $sortDirection,
        ]);
    }

    #[Route('/new', name: 'app_blencours_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ManagerRegistry $doctrine,
        BlencoursRepository $blencoursRepository
    ): Response {
        $blencour = new Blencours();
        // Par cohérence avec ton code initial, statut par défaut :
        $blencour->setStatut('En cours');

        $form = $this->createForm(BlencoursType::class, $blencour);
        $form->handleRequest($request);

        // Création "mono-BL" via le formulaire Symfony existant
        if ($form->isSubmitted() && $form->isValid()) {
            $numBl = $blencour->getNumBl();
            $existingBl = $blencoursRepository->findOneBy(['numBl' => $numBl]);

            if ($existingBl) {
                $this->addFlash('error', sprintf('Le numéro de BL "%s" existe déjà. Veuillez utiliser un autre numéro.', $numBl));
            } else {
                $blencour->setAdddate(new \DateTimeImmutable());
                $blencoursRepository->save($blencour);

                $this->addFlash('success', 'Le BL a été créé avec succès.');
                return $this->redirectToRoute('app_blencours_index');
            }
        }

        // Liste de BL du jour (non encore dans Blencours), CI9 en premier
        $availableBlList = $this->getAvailableBlForToday($entityManager);
        $stats = $this->getBlStatsForToday($entityManager);

        // Stats CI9 vs autres
        $ci9Count = 0;
        $otherCount = 0;
        foreach ($availableBlList as $bl) {
            $codeClient = $bl['Code_Client'] ?? '';
            if (strpos($codeClient, 'CI9') === 0) { $ci9Count++; } else { $otherCount++; }
        }

        return $this->render('blencours/new.html.twig', [
            'blencour'         => $blencour,
            'form'             => $form,
            'todayBlList'      => $availableBlList,
            'totalBlToday'     => $stats['total'],
            'availableBlCount' => $stats['available'],
            'existingBlCount'  => $stats['existing'],
            'ci9Count'         => $ci9Count,
            'otherCount'       => $otherCount,
        ]);
    }

    /**
     * AJOUT MULTIPLE — crée plusieurs Blencours en une fois
     */
    #[Route('/bulk-create', name: 'app_blencours_bulk_create', methods: ['POST'])]
    public function bulkCreate(
        Request $request,
        EntityManagerInterface $em,
        BlencoursRepository $blencoursRepository,
        ManagerRegistry $doctrine
    ): Response {
        if (!$this->isCsrfTokenValid('bulk_create_blencours', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_blencours_new');
        }

        $selected = $request->request->all('selected_bls'); // array de No_Bl
        if (!is_array($selected) || count($selected) === 0) {
            $this->addFlash('info', 'Aucun BL sélectionné.');
            return $this->redirectToRoute('app_blencours_new');
        }

        // On peut décider d’un statut par défaut (à défaut de Statut_Cde)
        $defaultStatus = $request->request->get('default_status', 'En cours');

        // On va chercher (optionnel) des infos des BL sources pour setter le statut si dispo
        $suiviRepo = $doctrine->getRepository('App\Entity\Suividupreparationdujour');

        $created = 0;
        $skipped = 0;

        foreach ($selected as $noBl) {
            if (!$noBl) { continue; }

            // Ignorer si déjà présent
            $exists = $blencoursRepository->findOneBy(['numBl' => $noBl]);
            if ($exists) { $skipped++; continue; }

            // Optionnel : récupérer la ligne source pour récupérer Statut_Cde
            $source = $suiviRepo->findOneBy(['No_Bl' => $noBl]);

            $entity = new Blencours();
            $entity->setNumBl($noBl);

            // Déterminer le statut
            $status = $defaultStatus;
            if ($source && method_exists($source, 'getStatutCde')) {
                $sourceStatus = $source->getStatutCde();
                if (is_string($sourceStatus) && $sourceStatus !== '') {
                    $status = $sourceStatus;
                }
            }
            $entity->setStatut($status);

            // Date ajout
            $entity->setAdddate(new \DateTimeImmutable());

            // Flags picking : par défaut à false (adapte si tu veux)
            $entity->setPickingok(false);
            $entity->setPickingnok(false);

            $em->persist($entity);
            $created++;
        }

        $em->flush();

        if ($created > 0) {
            $this->addFlash('success', "Création multiple effectuée : $created BL créés.");
        }
        if ($skipped > 0) {
            $this->addFlash('info', "$skipped BL ignorés (déjà existants).");
        }

        return $this->redirectToRoute('app_blencours_index');
    }

    #[Route('/clear-confirm', name: 'app_blencours_clear_confirm', methods: ['GET'])]
    public function clearConfirm(BlencoursRepository $blencoursRepository): Response
    {
        $totalCount = $blencoursRepository->count([]);
        return $this->render('blencours/clear_confirm.html.twig', [
            'totalCount' => $totalCount,
        ]);
    }

    #[Route('/clear-all', name: 'app_blencours_clear_all', methods: ['POST'])]
    public function clearAll(Request $request, BlencoursRepository $blencoursRepository): Response
    {
        if (!$this->isCsrfTokenValid('clear_all_blencours', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_blencours_index');
        }

        try {
            $countBefore = $blencoursRepository->count([]);
            if ($countBefore > 0) {
                $blencoursRepository->clearAll();
                $this->addFlash('success', "Tous les BL ont été supprimés ($countBefore enregistrements).");
            } else {
                $this->addFlash('info', 'Aucun BL à supprimer.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_blencours_index');
    }

    #[Route('/clear-by-status', name: 'app_blencours_clear_by_status', methods: ['POST'])]
    public function clearByStatus(Request $request, BlencoursRepository $blencoursRepository): Response
    {
        if (!$this->isCsrfTokenValid('clear_by_status', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_blencours_index');
        }

        $status = $request->request->get('status');
        $valid = ['En attente', 'En cours', 'Traité'];
        if (!in_array($status, $valid, true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_blencours_index');
        }

        try {
            $deleted = $blencoursRepository->deleteByStatus($status);
            if ($deleted > 0) {
                $this->addFlash('success', "Suppression OK : $deleted BL avec le statut \"$status\".");
            } else {
                $this->addFlash('info', "Aucun BL avec le statut \"$status\".");
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_blencours_index');
    }

    #[Route('/get-bl-data/{blNumber}', name: 'app_blencours_get_bl_data', methods: ['GET'])]
    public function getBlData(string $blNumber, ManagerRegistry $doctrine): JsonResponse
    {
        $repo = $doctrine->getRepository('App\Entity\Suividupreparationdujour');
        $blData = $repo->findBy(['No_Bl' => $blNumber]);

        $formatted = [];
        foreach ($blData as $bl) {
            $formatted[] = [
                'No_Bl'        => $bl->getNoBl(),
                'Client'       => $bl->getClient(),
                'Code_Client'  => $bl->getCodeClient(),
                'Transporteur' => $bl->getTransporteur(),
                'No_Cmd'       => $bl->getNoCmd(),
                'Date_liv'     => $bl->getDateLiv() ? $bl->getDateLiv()->format('Y-m-d') : null,
                'Statut_Cde'   => $bl->getStatutCde(),
                'Preparateur'  => $bl->getPreparateur(),
            ];
        }

        return $this->json(['success' => true, 'data' => $formatted]);
    }

    #[Route('/check-bl-exists/{numBl}', name: 'app_blencours_check_exists', methods: ['GET'])]
    public function checkBlExists(string $numBl, BlencoursRepository $blencoursRepository): JsonResponse
    {
        return $this->json(['exists' => null !== $blencoursRepository->findOneBy(['numBl' => $numBl])]);
    }

    #[Route('/search/autocomplete', name: 'app_blencours_search_autocomplete', methods: ['GET'])]
    public function searchAutocomplete(Request $request, BlencoursRepository $blencoursRepository): JsonResponse
    {
        $term = $request->query->get('term', '');
        if (strlen($term) < 2) {
            return $this->json([]);
        }

        $results = $blencoursRepository->searchByNumBLOrStatut($term);
        $payload = [];
        foreach ($results as $blencour) {
            $payload[] = [
                'id'     => $blencour->getId(),
                'numBl'  => $blencour->getNumBl(),
                'statut' => $blencour->getStatut(),
                'date'   => $blencour->getAdddate() ? $blencour->getAdddate()->format('d/m/Y H:i') : '',
            ];
        }
        return $this->json($payload);
    }

    // ====== À LA FIN : routes avec {id} ======

    #[Route('/{id}', name: 'app_blencours_show', methods: ['GET'])]
    public function show(Blencours $blencour): Response
    {
        return $this->render('blencours/show.html.twig', [
            'blencour' => $blencour,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_blencours_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Blencours $blencour, BlencoursRepository $blencoursRepository): Response
    {
        $originalNumBl = $blencour->getNumBl();
        $form = $this->createForm(BlencoursType::class, $blencour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newNumBl = $blencour->getNumBl();
            if ($newNumBl !== $originalNumBl) {
                $existing = $blencoursRepository->findOneBy(['numBl' => $newNumBl]);
                if ($existing && $existing->getId() !== $blencour->getId()) {
                    $this->addFlash('error', sprintf('Le numéro de BL "%s" existe déjà.', $newNumBl));
                    return $this->render('blencours/edit.html.twig', [
                        'blencour' => $blencour,
                        'form'     => $form,
                    ]);
                }
            }

            $blencoursRepository->save($blencour);
            $this->addFlash('success', 'Le BL a été modifié avec succès.');
            return $this->redirectToRoute('app_blencours_index');
        }

        return $this->render('blencours/edit.html.twig', [
            'blencour' => $blencour,
            'form'     => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_blencours_delete', methods: ['POST'])]
    public function delete(Request $request, Blencours $blencour, BlencoursRepository $blencoursRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $blencour->getId(), $request->request->get('_token'))) {
            $blencoursRepository->remove($blencour);
            $this->addFlash('success', 'Le BL a été supprimé avec succès.');
        }
        return $this->redirectToRoute('app_blencours_index');
    }

    #[Route('/{id}/complete', name: 'app_blencours_complete', methods: ['POST'])]
    public function complete(Request $request, Blencours $blencour, BlencoursRepository $blencoursRepository): Response
    {
        if ($this->isCsrfTokenValid('complete' . $blencour->getId(), $request->request->get('_token'))) {
            $blencoursRepository->markAsCompleted($blencour);
            $this->addFlash('success', 'Le BL a été marqué comme "Traité".');
        }
        return $this->redirectToRoute('app_blencours_index');
    }

    #[Route('/{id}/update-status', name: 'app_blencours_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, Blencours $blencour, BlencoursRepository $blencoursRepository): JsonResponse
    {
        $map = ['pending' => 'En attente', 'processing' => 'En cours', 'completed' => 'Traité'];
        $new = $request->request->get('status');

        if (!isset($map[$new])) {
            return $this->json(['success' => false, 'message' => 'Statut invalide'], 400);
        }

        $blencour->setStatut($map[$new]);
        $blencoursRepository->save($blencour);

        return $this->json(['success' => true, 'status' => $map[$new]]);
    }

    // ======= PRIVÉ =======

    private function getAvailableBlForToday(EntityManagerInterface $entityManager): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

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

    private function getBlStatsForToday(EntityManagerInterface $entityManager): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

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
        $totalBlToday = (int) $totalQuery->getSingleScalarResult();

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
        $existingCount = (int) $existingQuery->getSingleScalarResult();

        return [
            'total'     => $totalBlToday,
            'existing'  => $existingCount,
            'available' => $totalBlToday - $existingCount,
        ];
    }
}
