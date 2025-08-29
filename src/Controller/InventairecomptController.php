<?php

namespace App\Controller;

use App\Entity\Inventairecomplet;
use App\Entity\InventaireComptage;
use App\Repository\InventairecompletRepository;
use App\Repository\InventaireComptageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
// CHANGEMENT ICI : Remplacer l'ancien import
use Symfony\Bundle\SecurityBundle\Security;

final class InventairecomptController extends AbstractController
{
    private InventairecompletRepository $repository;
    private InventaireComptageRepository $comptageRepository;
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(
        InventairecompletRepository $repository,
        InventaireComptageRepository $comptageRepository,
        EntityManagerInterface $entityManager,
        Security $security
    ) {
        $this->repository = $repository;
        $this->comptageRepository = $comptageRepository;
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    /**
     * Page d'accueil avec statistiques générales
     */
    #[Route('/inventairecompt', name: 'app_inventairecompt')]
    public function index(): Response
    {
        // Récupérer quelques statistiques pour l'accueil
        $totalProduits = $this->repository->count([]);
        $codesProduitsUniques = $this->repository->createQueryBuilder('i')
            ->select('COUNT(DISTINCT i.codeprod)')
            ->getQuery()
            ->getSingleScalarResult();

        // Statistiques des comptages récents
        $comptagesRecents = $this->comptageRepository->createQueryBuilder('c')
            ->select('COUNT(c.id) as total_comptages')
            ->where('c.date_comptage >= :date')
            ->setParameter('date', new \DateTimeImmutable('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('inventairecompt/index.html.twig', [
            'controller_name' => 'InventairecomptController',
            'total_produits' => $totalProduits,
            'codes_produits_uniques' => $codesProduitsUniques,
            'comptages_recents' => $comptagesRecents,
        ]);
    }

    /**
     * Recherche par code produit
     */
    #[Route('/inventairecompt/search', name: 'app_inventairecompt_search')]
    public function search(Request $request): Response
    {
        $codeprod = trim($request->query->get('codeprod', ''));
        $zone = $request->query->get('zone', '');
        $stockMin = $request->query->get('stock_min', 0);
        
        $results = [];
        $totalUvTotal = 0;

        if ($codeprod) {
            // Validation du code produit
            if (strlen($codeprod) < 2 || strlen($codeprod) > 50) {
                $this->addFlash('error', 'Le code produit doit contenir entre 2 et 50 caractères');
                return $this->redirectToRoute('app_inventairecompt');
            }

            // Requête pour grouper par emplacement et sommer les UV
            $qb = $this->repository->createQueryBuilder('i')
                ->select('i.emplacement, i.codeprod, i.dsignprod, i.zone, 
                         SUM(i.uvtotal) as total_uvtotal, 
                         SUM(i.urdispo) as total_urdispo,
                         SUM(i.ucdispo) as total_ucdispo,
                         MAX(i.dateentree) as dateentree,
                         i.nopal,
                         COUNT(i.id) as nb_lignes')
                ->where('i.codeprod = :codeprod')
                ->setParameter('codeprod', $codeprod);

            // Filtres optionnels
            if ($zone) {
                $qb->andWhere('i.zone = :zone')
                   ->setParameter('zone', $zone);
            }

            $qb->groupBy('i.emplacement, i.codeprod, i.dsignprod, i.zone, i.nopal')
               ->orderBy('i.emplacement', 'ASC');

            $results = $qb->getQuery()->getResult();
            
            // Filtrer par stock minimum côté PHP si nécessaire
            if ($stockMin > 0) {
                $results = array_filter($results, function($item) use ($stockMin) {
                    return ($item['total_uvtotal'] ?? 0) >= $stockMin;
                });
            }
            
            // Calculer le total général
            foreach ($results as $item) {
                $totalUvTotal += $item['total_uvtotal'] ?? 0;
            }
        }

        return $this->render('inventairecompt/search.html.twig', [
            'codeprod' => $codeprod,
            'results' => $results,
            'total_uvtotal' => $totalUvTotal,
            'count' => count($results),
            'zone' => $zone,
            'stock_min' => $stockMin,
        ]);
    }

    /**
     * Vue détaillée d'un produit
     */
    #[Route('/inventairecompt/detail/{id}', name: 'app_inventairecompt_detail')]
    public function detail(Inventairecomplet $inventaire): Response
    {
        if (!$inventaire) {
            throw $this->createNotFoundException('Inventaire non trouvé');
        }

        return $this->render('inventairecompt/detail.html.twig', [
            'inventaire' => $inventaire,
        ]);
    }

    /**
     * Liste des produits par zone
     */
    #[Route('/inventairecompt/zone/{zone}', name: 'app_inventairecompt_zone')]
    public function byZone(string $zone): Response
    {
        $results = $this->repository->findBy(['zone' => $zone], ['emplacement' => 'ASC']);
        
        return $this->render('inventairecompt/zone.html.twig', [
            'zone' => $zone,
            'results' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * Rapport d'inventaire - totaux par code produit
     */
    #[Route('/inventairecompt/rapport', name: 'app_inventairecompt_rapport')]
    public function rapport(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        // Requête pour grouper par code produit et calculer les totaux
        $qb = $this->repository->createQueryBuilder('i')
            ->select('i.codeprod, i.dsignprod, 
                     SUM(i.uvtotal) as total_uvtotal, 
                     SUM(i.urdispo) as total_urdispo, 
                     SUM(i.ucdispo) as total_ucdispo,
                     COUNT(i.id) as nb_emplacements')
            ->groupBy('i.codeprod, i.dsignprod')
            ->orderBy('i.codeprod', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $rapportData = $qb->getQuery()->getResult();

        // Compter le total pour la pagination
        $totalCount = $this->repository->createQueryBuilder('i')
            ->select('COUNT(DISTINCT i.codeprod)')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('inventairecompt/rapport.html.twig', [
            'rapport_data' => $rapportData,
            'current_page' => $page,
            'total_pages' => ceil($totalCount / $limit),
        ]);
    }

    /**
     * API JSON pour recherche Ajax
     */
    #[Route('/inventairecompt/api/search/{codeprod}', name: 'app_inventairecompt_api_search', methods: ['GET'])]
    public function apiSearch(string $codeprod): JsonResponse
    {
        // Validation
        if (empty(trim($codeprod))) {
            return $this->json(['error' => 'Code produit requis'], 400);
        }

        try {
            $results = $this->repository->findBy(['codeprod' => $codeprod]);
            
            $data = [];
            $totalUvTotal = 0;
            
            foreach ($results as $item) {
                // Gérer le cas où uvtotal est string au lieu d'int
                $uvtotal = is_numeric($item->getUvtotal()) ? (int)$item->getUvtotal() : 0;
                $totalUvTotal += $uvtotal;
                
                $data[] = [
                    'id' => $item->getId(),
                    'codeprod' => $item->getCodeprod(),
                    'dsignprod' => $item->getDsignprod(),
                    'emplacement' => $item->getEmplacement(),
                    'nopal' => $item->getNopal(),
                    'uvtotal' => $uvtotal,
                    'urdispo' => $item->getUrdispo(),
                    'ucdispo' => $item->getUcdispo(),
                    'zone' => $item->getZone(),
                    'dateentree' => $item->getDateentree()?->format('Y-m-d')
                ];
            }

            return $this->json([
                'codeprod' => $codeprod,
                'count' => count($data),
                'total_uvtotal' => $totalUvTotal,
                'items' => $data
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la recherche'], 500);
        }
    }

    /**
     * API pour suggestions de codes produits
     */
    #[Route('/inventairecompt/api/suggestions/{query}', name: 'app_inventairecompt_suggestions', methods: ['GET'])]
    public function suggestions(string $query): JsonResponse
    {
        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $suggestions = $this->repository->createQueryBuilder('i')
            ->select('DISTINCT i.codeprod, i.dsignprod')
            ->where('i.codeprod LIKE :query OR i.dsignprod LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->json($suggestions);
    }

    /**
     * Liste des zones disponibles
     */
    #[Route('/inventairecompt/zones', name: 'app_inventairecompt_zones')]
    public function zones(): Response
    {
        $zones = $this->repository->createQueryBuilder('i')
            ->select('DISTINCT i.zone')
            ->where('i.zone IS NOT NULL AND i.zone != \'\'')
            ->orderBy('i.zone', 'ASC')
            ->getQuery()
            ->enableResultCache(3600) // Cache 1 heure
            ->getResult();

        return $this->render('inventairecompt/zones.html.twig', [
            'zones' => array_column($zones, 'zone'),
        ]);
    }

    // ========================================
    // NOUVELLES MÉTHODES POUR LE COMPTAGE
    // ========================================

    /**
     * Page de comptage - Récupère depuis Inventairecomplet, affiche pour comptage
     */
    #[Route('/inventairecompt/comptage/{codeprod}', name: 'app_inventairecompt_comptage')]
    public function comptage(string $codeprod, Request $request): Response
    {
        // ÉTAPE 1: Récupérer les données théoriques depuis votre entité existante
        $qb = $this->repository->createQueryBuilder('i')
            ->select('i.emplacement, i.codeprod, i.dsignprod, i.zone, 
                     SUM(i.uvtotal) as total_uvtotal,
                     SUM(i.urdispo) as total_urdispo,
                     SUM(i.ucdispo) as total_ucdispo,
                     MAX(i.dateentree) as dateentree,
                     i.nopal,
                     COUNT(i.id) as nb_lignes')
            ->where('i.codeprod = :codeprod')
            ->setParameter('codeprod', $codeprod)
            ->groupBy('i.emplacement, i.codeprod, i.dsignprod, i.zone, i.nopal')
            ->orderBy('i.emplacement', 'ASC');

        $donneesTheoriques = $qb->getQuery()->getResult();

        if (empty($donneesTheoriques)) {
            $this->addFlash('error', 'Aucun produit trouvé avec le code: ' . $codeprod);
            return $this->redirectToRoute('app_inventairecompt_search');
        }

        // ÉTAPE 2: Vérifier s'il existe déjà des comptages pour ce produit dans cette session
        $sessionId = $request->getSession()->getId();
        $comptagesExistants = $this->comptageRepository->findByCodeprodAndSession($codeprod, $sessionId);

        // Créer un map des comptages existants par emplacement
        $comptagesMap = [];
        foreach ($comptagesExistants as $comptage) {
            $comptagesMap[$comptage->getEmplacement()] = $comptage;
        }

        // ÉTAPE 3: Récupérer les statistiques si des comptages existent
        $statistiques = null;
        if (!empty($comptagesExistants)) {
            $statistiques = $this->comptageRepository->getStatistiquesComptage($codeprod, $sessionId);
        }

        return $this->render('inventairecompt/comptage.html.twig', [
            'codeprod' => $codeprod,
            'donnees_theoriques' => $donneesTheoriques,
            'comptages_existants' => $comptagesMap,
            'session_id' => $sessionId,
            'statistiques' => $statistiques,
            'produit_designation' => $donneesTheoriques[0]['dsignprod'] ?? '',
        ]);
    }

    /**
     * Sauvegarde des comptages dans la nouvelle entité InventaireComptage
     */
    #[Route('/inventairecompt/save-counts', name: 'app_inventairecompt_save_counts', methods: ['POST'])]
    public function saveCounts(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data || !isset($data['counts'])) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }

        $sessionId = $request->getSession()->getId();
        $operateur = $this->security->getUser()?->getUserIdentifier() ?? 'anonymous';
        $savedCount = 0;
        $updatedCount = 0;

        try {
            $this->entityManager->beginTransaction();

            foreach ($data['counts'] as $countData) {
                // Vérifier si un comptage existe déjà pour cet emplacement
                $existingComptage = $this->comptageRepository->findOneBy([
                    'codeprod' => $countData['codeprod'],
                    'emplacement' => $countData['emplacement'],
                    'session_inventaire' => $sessionId
                ]);

                if ($existingComptage) {
                    // Mettre à jour le comptage existant
                    $comptage = $existingComptage;
                    $comptage->setQteComptee($countData['counted']);
                    $comptage->setValide($countData['validated'] ?? false);
                    $comptage->setDateComptage(new \DateTimeImmutable());
                    $updatedCount++;
                } else {
                    // Créer un nouveau comptage
                    $comptage = new InventaireComptage();
                    $comptage->setCodeprod($countData['codeprod']);
                    $comptage->setDsignprod($countData['designation'] ?? '');
                    $comptage->setEmplacement($countData['emplacement']);
                    $comptage->setNopal($countData['nopal'] ?? null);
                    $comptage->setZone($countData['zone'] ?? null);
                    $comptage->setQteTheorique($countData['theoretical']);
                    $comptage->setQteComptee($countData['counted']);
                    $comptage->setSessionInventaire($sessionId);
                    $comptage->setOperateur($operateur);
                    $comptage->setValide($countData['validated'] ?? false);
                    $savedCount++;
                }

                // Ajouter commentaire si présent
                if (isset($countData['comment']) && !empty($countData['comment']['text'])) {
                    $comptage->setCommentaire($countData['comment']['text']);
                    $comptage->setTypeEcart($countData['comment']['type'] ?? null);
                }

                $this->entityManager->persist($comptage);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $this->json([
                'success' => true,
                'message' => "Sauvegarde réussie",
                'saved_count' => $savedCount,
                'updated_count' => $updatedCount,
                'total_count' => $savedCount + $updatedCount
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return $this->json([
                'error' => 'Erreur lors de la sauvegarde: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rapport d'écarts basé sur les comptages sauvegardés
     */
    #[Route('/inventairecompt/rapport-ecarts/{codeprod}', name: 'app_inventairecompt_rapport_ecarts')]
    public function rapportEcarts(string $codeprod, Request $request): Response
    {
        $sessionId = $request->getSession()->getId();
        
        // Récupérer tous les comptages pour ce produit
        $comptages = $this->comptageRepository->findByCodeprodAndSession($codeprod, $sessionId);
        
        if (empty($comptages)) {
            $this->addFlash('warning', 'Aucun comptage trouvé pour ce produit dans cette session.');
            return $this->redirectToRoute('app_inventairecompt_comptage', ['codeprod' => $codeprod]);
        }

        // Récupérer les statistiques
        $statistiques = $this->comptageRepository->getStatistiquesComptage($codeprod, $sessionId);
        
        // Récupérer les écarts majeurs
        $ecartsMajeurs = $this->comptageRepository->findEcartsMajeurs($codeprod, $sessionId, 10.0);

        return $this->render('inventairecompt/rapport_ecarts.html.twig', [
            'codeprod' => $codeprod,
            'comptages' => $comptages,
            'statistiques' => $statistiques,
            'ecarts_majeurs' => $ecartsMajeurs,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Export CSV des comptages et écarts
     */
    #[Route('/inventairecompt/export-comptages/{codeprod}', name: 'app_inventairecompt_export_comptages')]
    public function exportComptages(string $codeprod, Request $request): Response
    {
        $sessionId = $request->getSession()->getId();
        $comptages = $this->comptageRepository->findByCodeprodAndSession($codeprod, $sessionId);

        if (empty($comptages)) {
            $this->addFlash('error', 'Aucun comptage à exporter pour ce produit.');
            return $this->redirectToRoute('app_inventairecompt_comptage', ['codeprod' => $codeprod]);
        }

        $csv = "Emplacement,Code Produit,Designation,Zone,Palette,Qty Theorique,Qty Comptee,Ecart,Ecart %,Type Ecart,Valide,Commentaire,Operateur,Date Comptage\n";
        
        foreach ($comptages as $comptage) {
            $pourcentageEcart = $comptage->getPourcentageEcart();
            
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%d","%d","%d","%.2f%%","%s","%s","%s","%s","%s"' . "\n",
                $comptage->getEmplacement(),
                $comptage->getCodeprod(),
                $comptage->getDsignprod() ?? '',
                $comptage->getZone() ?? '',
                $comptage->getNopal() ?? '',
                $comptage->getQteTheorique(),
                $comptage->getQteComptee(),
                $comptage->getEcart(),
                $pourcentageEcart ? number_format($pourcentageEcart, 2) : '0.00',
                $comptage->getTypeEcartAuto(),
                $comptage->isValide() ? 'Oui' : 'Non',
                str_replace('"', '""', $comptage->getCommentaire() ?? ''),
                $comptage->getOperateur(),
                $comptage->getDateComptage()->format('d/m/Y H:i')
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 
            'attachment; filename="comptage_' . $codeprod . '_' . date('Y-m-d_H-i') . '.csv"');

        return $response;
    }

    /**
     * API pour récupérer les comptages existants
     */
    #[Route('/inventairecompt/api/comptages/{codeprod}', name: 'app_inventairecompt_api_comptages', methods: ['GET'])]
    public function apiComptages(string $codeprod, Request $request): JsonResponse
    {
        $sessionId = $request->getSession()->getId();
        $comptages = $this->comptageRepository->findByCodeprodAndSession($codeprod, $sessionId);
        
        $data = [];
        foreach ($comptages as $comptage) {
            $data[] = [
                'emplacement' => $comptage->getEmplacement(),
                'qte_theorique' => $comptage->getQteTheorique(),
                'qte_comptee' => $comptage->getQteComptee(),
                'ecart' => $comptage->getEcart(),
                'pourcentage_ecart' => $comptage->getPourcentageEcart(),
                'valide' => $comptage->isValide(),
                'commentaire' => $comptage->getCommentaire(),
                'type_ecart' => $comptage->getTypeEcart(),
                'operateur' => $comptage->getOperateur(),
                'date_comptage' => $comptage->getDateComptage()->format('d/m/Y H:i')
            ];
        }

        $statistiques = null;
        if (!empty($comptages)) {
            $statistiques = $this->comptageRepository->getStatistiquesComptage($codeprod, $sessionId);
        }

        return $this->json([
            'codeprod' => $codeprod,
            'session_id' => $sessionId,
            'comptages' => $data,
            'count' => count($data),
            'statistiques' => $statistiques
        ]);
    }

    /**
     * Reset des comptages pour un produit
     */
    #[Route('/inventairecompt/reset-comptages/{codeprod}', name: 'app_inventairecompt_reset_comptages', methods: ['POST'])]
    public function resetComptages(string $codeprod, Request $request): JsonResponse
    {
        $sessionId = $request->getSession()->getId();
        
        try {
            $deletedCount = $this->comptageRepository->deleteComptagesSession($codeprod, $sessionId);
            
            return $this->json([
                'success' => true,
                'message' => "$deletedCount comptages supprimés",
                'deleted_count' => $deletedCount
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validation en lot d'un produit
     */
    #[Route('/inventairecompt/validate-all/{codeprod}', name: 'app_inventairecompt_validate_all', methods: ['POST'])]
    public function validateAll(string $codeprod, Request $request): JsonResponse
    {
        $sessionId = $request->getSession()->getId();
        
        try {
            $comptages = $this->comptageRepository->findByCodeprodAndSession($codeprod, $sessionId);
            $validatedCount = 0;
            
            foreach ($comptages as $comptage) {
                if (!$comptage->isValide()) {
                    $comptage->setValide(true);
                    $comptage->setDateValidation(new \DateTimeImmutable());
                    $this->entityManager->persist($comptage);
                    $validatedCount++;
                }
            }
            
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => "$validatedCount comptages validés",
                'validated_count' => $validatedCount
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la validation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard des sessions de comptage
     */
    #[Route('/inventairecompt/sessions', name: 'app_inventairecompt_sessions')]
    public function sessions(Request $request): Response
    {
        $sessionId = $request->getSession()->getId();
        
        // Récupérer le résumé de la session actuelle
        $resumeSession = $this->comptageRepository->getResumeSession($sessionId);
        
        return $this->render('inventairecompt/sessions.html.twig', [
            'session_id' => $sessionId,
            'resume_session' => $resumeSession,
        ]);
    }

    /**
     * Statistiques générales des comptages
     */
    #[Route('/inventairecompt/statistiques', name: 'app_inventairecompt_statistiques')]
    public function statistiques(): Response
    {
        // Statistiques globales
        $stats = [
            'total_comptages' => $this->comptageRepository->count([]),
            'comptages_valides' => $this->comptageRepository->count(['valide' => true]),
            'produits_comptes' => $this->comptageRepository->createQueryBuilder('c')
                ->select('COUNT(DISTINCT c.codeprod)')
                ->getQuery()
                ->getSingleScalarResult(),
            'comptages_avec_ecarts' => $this->comptageRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.ecart != 0')
                ->getQuery()
                ->getSingleScalarResult(),
        ];

        // Top des écarts
        $topEcarts = $this->comptageRepository->createQueryBuilder('c')
            ->select('c.codeprod, c.dsignprod, SUM(c.ecart) as ecart_total, COUNT(c.id) as nb_comptages')
            ->where('c.ecart != 0')
            ->groupBy('c.codeprod, c.dsignprod')
            ->orderBy('ABS(SUM(c.ecart))', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('inventairecompt/statistiques.html.twig', [
            'stats' => $stats,
            'top_ecarts' => $topEcarts,
        ]);
    }
}