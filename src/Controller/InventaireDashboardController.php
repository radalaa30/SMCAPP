<?php

namespace App\Controller;

use App\Repository\InventairecompletRepository;
use App\Repository\SuividupreparationdujourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_ADMIN')]
class InventaireDashboardController extends AbstractController
{
    private $inventaireRepository;
    private $suiviRepository;

    public function __construct(
        InventairecompletRepository $inventaireRepository,
        SuividupreparationdujourRepository $suiviRepository
    ) {
        $this->inventaireRepository = $inventaireRepository;
        $this->suiviRepository = $suiviRepository;
    }

    /**
     * Formate un nombre avec des virgules (ex: 3228134 → 3,228,134)
     */
    private function formatWithCommas(string $number): string
    {
        // Enlever les virgules d'abord au cas où
        $cleanNumber = str_replace(',', '', $number);
        
        // Ajouter les virgules
        if (strlen($cleanNumber) >= 4) {
            return number_format((int) $cleanNumber);
        }
        
        return $cleanNumber;
    }

    #[Route('/dashboard/inventaire', name: 'app_dashboard_inventaire')]
    public function index(): Response
    {
        return $this->render('inventaire_dashboard/index.html.twig');
    }

    #[Route('/dashboard/inventaire/recherche-simple', name: 'app_dashboard_inventaire_recherche_simple', methods: ['GET'])]
    public function rechercheSimple(Request $request): JsonResponse
    {
        $adresse = $request->query->get('adresse');

        if (!$adresse) {
            return $this->json(['error' => 'Adresse requise'], 400);
        }

        try {
            // 1. Recherche de tous les produits à cette adresse dans l'inventaire
            $produits = $this->inventaireRepository->createQueryBuilder('i')
                ->where('i.emplacement = :adresse')
                ->setParameter('adresse', $adresse)
                ->orderBy('i.codeprod', 'ASC')
                ->getQuery()
                ->getResult();

            // 2. Calcul du total UV et collecte des numéros de palettes
            $totalUv = 0;
            $numerosNopalinfo = [];
            $detailsProduits = [];

            foreach ($produits as $produit) {
                $totalUv += $produit->getUvtotal();
                
                if ($produit->getNopalinfo()) {
                    $numerosNopalinfo[] = $produit->getNopalinfo();
                }
                
                $detailsProduits[] = [
                    'id' => $produit->getId(),
                    'codeProduit' => $produit->getCodeprod(),
                    'designation' => $produit->getDsignprod(),
                    'uvTotal' => $produit->getUvtotal(),
                    'nopalinfo' => $produit->getNopalinfo(),
                    'zone' => $produit->getZone(),
                    'emplacement' => $produit->getEmplacement()
                ];
            }

            // 3. Variables pour les calculs
            $totalNbArtTraite = 0;           // Articles traités (valider IS NULL)
            $totalNbArtNonFlashe = 0;        // Articles en attente (flasher IS NULL ET valider IS NULL)
            $totalNbArtFlasheNonValide = 0;  // Articles flashés 'Ok' mais non validés
            $nombreLignesSuivi = 0;
            $nombreLignesNonFlashees = 0;
            $nombreLignesFlashees = 0;
            $detailsSuivi = [];
            $detailParNopalinfo = [];
            
            // Supprimer les doublons des nopalinfo
            $numerosNopalinfoUniques = array_unique(array_filter($numerosNopalinfo));

            // Créer les dates de début et fin pour aujourd'hui
            $aujourdhui = new \DateTime('today');
            $demain = new \DateTime('tomorrow');

            if (!empty($numerosNopalinfoUniques)) {
                // Pour chaque nopalinfo, calculer les totaux
                foreach ($numerosNopalinfoUniques as $nopalinfo) {
                    $nopalinfoNettoye = str_replace(',', '', trim($nopalinfo));
                    
                    // REQUÊTE 1: Articles NON flashés (en attente) - Flasher IS NULL ET valider IS NULL
                    $nbArtNonFlashePourCeNopal = $this->suiviRepository->createQueryBuilder('s')
                        ->select('SUM(s.Nb_art) as totalNbArt')
                        ->where('(TRIM(s.No_Pal) = :nopal1 OR TRIM(s.No_Pal) = :nopal2 OR TRIM(s.No_Pal) = :nopal3)')
                        ->andWhere('s.Flasher IS NULL')
                        ->andWhere('s.valider IS NULL')
                        ->andWhere('s.updatedAt >= :aujourdhui')
                        ->andWhere('s.updatedAt < :demain')
                        ->setParameter('nopal1', $nopalinfoNettoye)
                        ->setParameter('nopal2', $this->formatWithCommas($nopalinfoNettoye))
                        ->setParameter('nopal3', ' ' . $this->formatWithCommas($nopalinfoNettoye) . ' ')
                        ->setParameter('aujourdhui', $aujourdhui)
                        ->setParameter('demain', $demain)
                        ->getQuery()
                        ->getSingleScalarResult();

                    $nombreLignesNonFlashesPourCeNopal = $this->suiviRepository->createQueryBuilder('s')
                        ->select('COUNT(s.id) as nombreLignes')
                        ->where('(TRIM(s.No_Pal) = :nopal1 OR TRIM(s.No_Pal) = :nopal2 OR TRIM(s.No_Pal) = :nopal3)')
                        ->andWhere('s.Flasher IS NULL')
                        ->andWhere('s.valider IS NULL')
                        ->andWhere('s.updatedAt >= :aujourdhui')
                        ->andWhere('s.updatedAt < :demain')
                        ->setParameter('nopal1', $nopalinfoNettoye)
                        ->setParameter('nopal2', $this->formatWithCommas($nopalinfoNettoye))
                        ->setParameter('nopal3', ' ' . $this->formatWithCommas($nopalinfoNettoye) . ' ')
                        ->setParameter('aujourdhui', $aujourdhui)
                        ->setParameter('demain', $demain)
                        ->getQuery()
                        ->getSingleScalarResult();

                    // REQUÊTE 2: Articles flashés 'Ok' mais non validés - Flasher = 'Ok' ET valider IS NULL
                    $nbArtFlasheNonValidePourCeNopal = $this->suiviRepository->createQueryBuilder('s')
                        ->select('SUM(s.Nb_art) as totalNbArt')
                        ->where('(TRIM(s.No_Pal) = :nopal1 OR TRIM(s.No_Pal) = :nopal2 OR TRIM(s.No_Pal) = :nopal3)')
                        ->andWhere('s.Flasher = :flasherOk')
                        ->andWhere('s.valider IS NULL')
                        ->andWhere('s.updatedAt >= :aujourdhui')
                        ->andWhere('s.updatedAt < :demain')
                        ->setParameter('nopal1', $nopalinfoNettoye)
                        ->setParameter('nopal2', $this->formatWithCommas($nopalinfoNettoye))
                        ->setParameter('nopal3', ' ' . $this->formatWithCommas($nopalinfoNettoye) . ' ')
                        ->setParameter('flasherOk', 'Ok')
                        ->setParameter('aujourdhui', $aujourdhui)
                        ->setParameter('demain', $demain)
                        ->getQuery()
                        ->getSingleScalarResult();

                    $nombreLignesFlasheesPourCeNopal = $this->suiviRepository->createQueryBuilder('s')
                        ->select('COUNT(s.id) as nombreLignes')
                        ->where('(TRIM(s.No_Pal) = :nopal1 OR TRIM(s.No_Pal) = :nopal2 OR TRIM(s.No_Pal) = :nopal3)')
                        ->andWhere('s.Flasher = :flasherOk')
                        ->andWhere('s.valider IS NULL')
                        ->andWhere('s.updatedAt >= :aujourdhui')
                        ->andWhere('s.updatedAt < :demain')
                        ->setParameter('nopal1', $nopalinfoNettoye)
                        ->setParameter('nopal2', $this->formatWithCommas($nopalinfoNettoye))
                        ->setParameter('nopal3', ' ' . $this->formatWithCommas($nopalinfoNettoye) . ' ')
                        ->setParameter('flasherOk', 'Ok')
                        ->setParameter('aujourdhui', $aujourdhui)
                        ->setParameter('demain', $demain)
                        ->getQuery()
                        ->getSingleScalarResult();

                    // REQUÊTE 3: Total traité (valider IS NULL) - comme avant
                    $nbArtTraitePourCeNopal = $this->suiviRepository->createQueryBuilder('s')
                        ->select('SUM(s.Nb_art) as totalNbArt')
                        ->where('(TRIM(s.No_Pal) = :nopal1 OR TRIM(s.No_Pal) = :nopal2 OR TRIM(s.No_Pal) = :nopal3)')
                        ->andWhere('s.valider IS NULL')
                        ->andWhere('s.updatedAt >= :aujourdhui')
                        ->andWhere('s.updatedAt < :demain')
                        ->setParameter('nopal1', $nopalinfoNettoye)
                        ->setParameter('nopal2', $this->formatWithCommas($nopalinfoNettoye))
                        ->setParameter('nopal3', ' ' . $this->formatWithCommas($nopalinfoNettoye) . ' ')
                        ->setParameter('aujourdhui', $aujourdhui)
                        ->setParameter('demain', $demain)
                        ->getQuery()
                        ->getSingleScalarResult();

                    $detailParNopalinfo[] = [
                        'nopalinfo' => $nopalinfo,
                        'nbArtTraite' => (int) ($nbArtTraitePourCeNopal ?? 0),
                        'nbArtNonFlashe' => (int) ($nbArtNonFlashePourCeNopal ?? 0),
                        'nbArtFlasheNonValide' => (int) ($nbArtFlasheNonValidePourCeNopal ?? 0),
                        'nombreLignesNonFlashees' => (int) ($nombreLignesNonFlashesPourCeNopal ?? 0),
                        'nombreLignesFlashees' => (int) ($nombreLignesFlasheesPourCeNopal ?? 0)
                    ];

                    $totalNbArtTraite += (int) ($nbArtTraitePourCeNopal ?? 0);
                    $totalNbArtNonFlashe += (int) ($nbArtNonFlashePourCeNopal ?? 0);
                    $totalNbArtFlasheNonValide += (int) ($nbArtFlasheNonValidePourCeNopal ?? 0);
                    $nombreLignesNonFlashees += (int) ($nombreLignesNonFlashesPourCeNopal ?? 0);
                    $nombreLignesFlashees += (int) ($nombreLignesFlasheesPourCeNopal ?? 0);
                }

                $nombreLignesSuivi = $nombreLignesNonFlashees + $nombreLignesFlashees;

                // Récupération des détails des mouvements (valider IS NULL)
                $qb = $this->suiviRepository->createQueryBuilder('s')
                    ->where('s.valider IS NULL')
                    ->andWhere('s.updatedAt >= :aujourdhui')
                    ->andWhere('s.updatedAt < :demain')
                    ->setParameter('aujourdhui', $aujourdhui)
                    ->setParameter('demain', $demain)
                    ->orderBy('s.No_Pal', 'ASC')
                    ->addOrderBy('s.updatedAt', 'DESC');

                // Construction des conditions pour tous les formats possibles
                $allConditions = [];
                $paramIndex = 0;
                foreach ($numerosNopalinfoUniques as $nopalinfo) {
                    $nopalinfoNettoye = str_replace(',', '', trim($nopalinfo));
                    $nopalinfoAvecVirgules = $this->formatWithCommas($nopalinfoNettoye);
                    
                    $allConditions[] = "TRIM(s.No_Pal) = :p$paramIndex";
                    $qb->setParameter("p$paramIndex", $nopalinfoNettoye);
                    $paramIndex++;
                    
                    $allConditions[] = "TRIM(s.No_Pal) = :p$paramIndex";
                    $qb->setParameter("p$paramIndex", $nopalinfoAvecVirgules);
                    $paramIndex++;
                    
                    $allConditions[] = "TRIM(s.No_Pal) = :p$paramIndex";
                    $qb->setParameter("p$paramIndex", ' ' . $nopalinfoAvecVirgules . ' ');
                    $paramIndex++;
                }
                
                if (!empty($allConditions)) {
                    $qb->andWhere('(' . implode(' OR ', $allConditions) . ')');
                }

                $mouvementsSuivi = $qb->getQuery()->getResult();

                foreach ($mouvementsSuivi as $mouvement) {
                    $detailsSuivi[] = [
                        'id' => $mouvement->getId(),
                        'codeProduit' => $mouvement->getCodeProduit(),
                        'noPal' => $mouvement->getNoPal(),
                        'nbArt' => $mouvement->getNbArt(),
                        'client' => $mouvement->getClient(),
                        'zone' => $mouvement->getZone(),
                        'adresse' => $mouvement->getAdresse(),
                        'flasher' => $mouvement->getFlasher(),
                        'valider' => $mouvement->getValider() ? $mouvement->getValider()->format('Y-m-d H:i:s') : null,
                        'dateUpdate' => $mouvement->getUpdatedAt()?->format('d/m/Y H:i:s')
                    ];
                }
            }

            // CALCUL DU STOCK LIVE
            $stockInventaire = $totalUv;
            $articlesEnAttente = $totalNbArtNonFlashe;
            $articlesEnCours = $totalNbArtFlasheNonValide;
            $stockLive = $stockInventaire + $articlesEnAttente - $articlesEnCours;

            return $this->json([
                'adresse' => $adresse,
                'inventaire' => [
                    'totalUv' => $totalUv,
                    'nombreProduits' => count($produits),
                    'numerosNopalinfo' => $numerosNopalinfoUniques,
                    'produits' => $detailsProduits
                ],
                'suivi' => [
                    'totalNbArtTraite' => $totalNbArtTraite,              // Total traité (valider IS NULL)
                    'totalNbArtNonFlashe' => $totalNbArtNonFlashe,        // Articles en attente
                    'totalNbArtFlasheNonValide' => $totalNbArtFlasheNonValide, // Articles flashés 'Ok'
                    'nombreLignes' => $nombreLignesSuivi,
                    'nombreLignesNonFlashees' => $nombreLignesNonFlashees,
                    'nombreLignesFlashees' => $nombreLignesFlashees,
                    'detailParNopalinfo' => $detailParNopalinfo,
                    'mouvements' => $detailsSuivi
                ],
                'stocks' => [
                    'stockInventaire' => $stockInventaire,               // Stock de base
                    'articlesEnAttente' => $articlesEnAttente,           // + Articles pas encore flashés
                    'articlesEnCours' => $articlesEnCours,              // - Articles flashés 'Ok'
                    'stockLive' => $stockLive                           // = STOCK LIVE RÉEL
                ],
                'difference' => [
                    'uvRestantApresTraitement' => $totalUv - $totalNbArtTraite,  // Comme avant
                    'stockReel' => $stockLive - $totalNbArtTraite               // Stock réel après tout traitement
                ],
                'detail_calcul' => [
                    'formule' => 'Stock Live = Inventaire + En Attente - En Cours',
                    'calcul' => "$stockInventaire + $articlesEnAttente - $articlesEnCours = $stockLive"
                ],
                'criteres_filtres' => [
                    'articles_traites' => 'valider IS NULL ET updatedAt = ' . $aujourdhui->format('Y-m-d'),
                    'articles_en_attente' => 'Flasher IS NULL ET valider IS NULL ET updatedAt = ' . $aujourdhui->format('Y-m-d'),
                    'articles_en_cours' => 'Flasher = \'Ok\' ET valider IS NULL ET updatedAt = ' . $aujourdhui->format('Y-m-d')
                ],
                'date_recherche' => $aujourdhui->format('d/m/Y')
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/dashboard/inventaire/test-simple', name: 'app_dashboard_inventaire_test_simple', methods: ['GET'])]
    public function testSimple(): JsonResponse
    {
        try {
            // Test de base
            $countInventaire = $this->inventaireRepository->createQueryBuilder('i')
                ->select('COUNT(i.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $countSuivi = $this->suiviRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Créer les dates pour aujourd'hui
            $aujourdhui = new \DateTime('today');
            $demain = new \DateTime('tomorrow');

            // === STATISTIQUES VALIDATION ===
            
            // Lignes NON validées (valider IS NULL)
            $countNonValide = $this->suiviRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.valider IS NULL')
                ->getQuery()
                ->getSingleScalarResult();

            // Lignes validées (valider IS NOT NULL)
            $countValide = $this->suiviRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.valider IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult();

            // === STATISTIQUES VALIDATION POUR AUJOURD'HUI ===
            
            // Lignes NON validées aujourd'hui (valider IS NULL ET updatedAt = aujourd'hui)
            $countNonValideAujourdhui = $this->suiviRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.valider IS NULL')
                ->andWhere('s.updatedAt >= :aujourdhui')
                ->andWhere('s.updatedAt < :demain')
                ->setParameter('aujourdhui', $aujourdhui)
                ->setParameter('demain', $demain)
                ->getQuery()
                ->getSingleScalarResult();

            // Lignes validées aujourd'hui (valider IS NOT NULL ET updatedAt = aujourd'hui)
            $countValideAujourdhui = $this->suiviRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.valider IS NOT NULL')
                ->andWhere('s.updatedAt >= :aujourdhui')
                ->andWhere('s.updatedAt < :demain')
                ->setParameter('aujourdhui', $aujourdhui)
                ->setParameter('demain', $demain)
                ->getQuery()
                ->getSingleScalarResult();

            // Total des lignes pour aujourd'hui
            $countTotalAujourdhui = $this->suiviRepository->createQueryBuilder('s')
                ->select('COUNT(s.id)')
                ->where('s.updatedAt >= :aujourdhui')
                ->andWhere('s.updatedAt < :demain')
                ->setParameter('aujourdhui', $aujourdhui)
                ->setParameter('demain', $demain)
                ->getQuery()
                ->getSingleScalarResult();

            // Exemples d'adresses
            $exemples = $this->inventaireRepository->createQueryBuilder('i')
                ->select('i.emplacement, i.codeprod, i.uvtotal, i.nopalinfo')
                ->where('i.emplacement IS NOT NULL')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            // Calcul des pourcentages
            $pourcentageNonValide = $countSuivi > 0 ? round(($countNonValide / $countSuivi) * 100, 2) : 0;
            $pourcentageValide = $countSuivi > 0 ? round(($countValide / $countSuivi) * 100, 2) : 0;
            
            $pourcentageNonValideAujourdhui = $countTotalAujourdhui > 0 ? round(($countNonValideAujourdhui / $countTotalAujourdhui) * 100, 2) : 0;
            $pourcentageValideAujourdhui = $countTotalAujourdhui > 0 ? round(($countValideAujourdhui / $countTotalAujourdhui) * 100, 2) : 0;

            return $this->json([
                'status' => 'OK',
                'date_recherche' => $aujourdhui->format('d/m/Y'),
                
                // Statistiques générales
                'total_inventaire' => $countInventaire,
                'total_suivi' => $countSuivi,
                
                // Statistiques de validation (tous les enregistrements)
                'validation_globale' => [
                    'total' => $countSuivi,
                    'non_valide' => $countNonValide,
                    'valide' => $countValide,
                    'pourcentage_non_valide' => $pourcentageNonValide,
                    'pourcentage_valide' => $pourcentageValide
                ],
                
                // Statistiques de validation pour aujourd'hui uniquement
                'validation_aujourdhui' => [
                    'total' => $countTotalAujourdhui,
                    'non_valide' => $countNonValideAujourdhui,
                    'valide' => $countValideAujourdhui,
                    'pourcentage_non_valide' => $pourcentageNonValideAujourdhui,
                    'pourcentage_valide' => $pourcentageValideAujourdhui
                ],
                
                'exemples_adresses' => $exemples,
                'message' => 'Test réussi - Statistiques de validation calculées'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur test: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/dashboard/inventaire/debug-recherche', name: 'app_dashboard_inventaire_debug_recherche', methods: ['GET'])]
    public function debugRecherche(Request $request): Response
    {
        $adresse = $request->query->get('adresse');
        
        if (!$adresse) {
            return new Response("❌ Adresse requise");
        }

        ob_start();
        
        echo "🔍 DEBUG COMPLET AVEC STOCK LIVE - Adresse reçue: "; var_dump($adresse); echo "\n";
        echo "=====================================\n\n";
        echo "📦 === PHASE 1: RECHERCHE INVENTAIRE ===\n\n";
        
        try {
            $produits = $this->inventaireRepository->createQueryBuilder('i')
                ->where('i.emplacement = :adresse')
                ->setParameter('adresse', $adresse)
                ->orderBy('i.codeprod', 'ASC')
                ->getQuery()
                ->getResult();

            echo "Nombre de produits trouvés: " . count($produits) . "\n\n";

            $totalUv = 0;
            $numerosNopalinfo = [];

            foreach ($produits as $produit) {
                echo "Produit: {$produit->getCodeprod()} | UV: {$produit->getUvtotal()} | Nopalinfo: '{$produit->getNopalinfo()}'\n";
                $totalUv += $produit->getUvtotal();
                
                if ($produit->getNopalinfo()) {
                    $numerosNopalinfo[] = $produit->getNopalinfo();
                }
            }

            echo "\n📊 RÉSULTATS INVENTAIRE:\n";
            echo "Total UV: $totalUv\n";
            $numerosNopalinfoUniques = array_unique(array_filter($numerosNopalinfo));
            echo "Numéros Nopalinfo uniques: [" . implode(', ', array_map(function($n) { return "'$n'"; }, $numerosNopalinfoUniques)) . "]\n";
            echo "Nombre de nopalinfo uniques: " . count($numerosNopalinfoUniques) . "\n\n";

            // Créer les dates pour aujourd'hui
            $aujourdhui = new \DateTime('today');
            $demain = new \DateTime('tomorrow');
            echo "🗓️ Date de recherche: " . $aujourdhui->format('d/m/Y') . "\n\n";

            echo "🚚 === PHASE 2: ANALYSE STOCK LIVE ===\n\n";
            
            if (!empty($numerosNopalinfoUniques)) {
                $totalNbArtTraite = 0;
                $totalNbArtNonFlashe = 0;
                $totalNbArtFlasheNonValide = 0;
                
                foreach ($numerosNopalinfoUniques as $nopalinfo) {
                    $nopalinfoNettoye = str_replace(',', '', trim($nopalinfo));
                    
                    echo "--- ANALYSE POUR NOPALINFO '$nopalinfo' ---\n";
                    
                    // Articles en attente (Flasher IS NULL ET valider IS NULL)
                    $nbArtNonFlashe = $this->suiviRepository->createQueryBuilder('s')
                        ->select('SUM(s.Nb_art) as totalNbArt')
                        ->where('(TRIM(s.No_Pal) = :nopal1 OR TRIM(s.No_Pal) = :nopal2 OR TRIM(s.No_Pal) = :nopal3)')
                        ->andWhere('s.Flasher IS NULL')
                        ->andWhere('s.valider IS NULL')
                        ->andWhere('s.updatedAt >= :aujourdhui')
                        ->andWhere('s.updatedAt < :demain')
                        ->setParameter('nopal1', $nopalinfoNettoye)
                        ->setParameter('nopal2', $this->formatWithCommas($nopalinfoNettoye))
                        ->setParameter('nopal3', ' ' . $this->formatWithCommas($nopalinfoNettoye) . ' ')
                        ->setParameter('aujourdhui', $aujourdhui)
                        ->setParameter('demain', $demain)
                        ->getQuery()
                        ->getSingleScalarResult();
                    
                    // Articles en cours (Flasher = 'Ok' ET valider IS NULL)
                    $nbArtFlasheNonValide = $this->suiviRepository->createQueryBuilder('s')
                        ->select('SUM(s.Nb_art) as totalNbArt')
                        ->where('(TRIM(s.No_Pal) = :nopal1 OR TRIM(s.No_Pal) = :nopal2 OR TRIM(s.No_Pal) = :nopal3)')
                        ->andWhere('s.Flasher = :flasherOk')
                        ->andWhere('s.valider IS NULL')
                        ->andWhere('s.updatedAt >= :aujourdhui')
                        ->andWhere('s.updatedAt < :demain')
                        ->setParameter('nopal1', $nopalinfoNettoye)
                        ->setParameter('nopal2', $this->formatWithCommas($nopalinfoNettoye))
                        ->setParameter('nopal3', ' ' . $this->formatWithCommas($nopalinfoNettoye) . ' ')
                        ->setParameter('flasherOk', 'Ok')
                        ->setParameter('aujourdhui', $aujourdhui)
                        ->setParameter('demain', $demain)
                        ->getQuery()
                        ->getSingleScalarResult();
                    
                    $nbArtNonFlashe = (int) ($nbArtNonFlashe ?? 0);
                    $nbArtFlasheNonValide = (int) ($nbArtFlasheNonValide ?? 0);
                    
                    echo "  Articles en attente (pas flashés): $nbArtNonFlashe\n";
                    echo "  Articles en cours (flashés 'Ok'): $nbArtFlasheNonValide\n";
                    
                    $totalNbArtNonFlashe += $nbArtNonFlashe;
                    $totalNbArtFlasheNonValide += $nbArtFlasheNonValide;
                }
                
                // Calcul du stock live
                $stockLive = $totalUv + $totalNbArtNonFlashe - $totalNbArtFlasheNonValide;
                
                echo "\n🧮 === CALCUL STOCK LIVE ===\n";
                echo "Stock Inventaire: $totalUv\n";
                echo "+ Articles en attente: $totalNbArtNonFlashe\n";
                echo "- Articles en cours: $totalNbArtFlasheNonValide\n";
                echo "= STOCK LIVE: $stockLive\n";
                echo "Formule: $totalUv + $totalNbArtNonFlashe - $totalNbArtFlasheNonValide = $stockLive\n";

            } else {
                echo "❌ Aucun nopalinfo trouvé dans l'inventaire\n";
            }

        } catch (\Exception $e) {
            echo "❌ Erreur: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
        
        $output = ob_get_clean();
        
        return new Response("<pre style='font-size: 12px; line-height: 1.4;'>$output</pre>", 200, ['Content-Type' => 'text/html']);
    }
}