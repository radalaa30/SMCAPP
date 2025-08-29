<?php

namespace App\Controller;

use App\Repository\InventairecompletRepository;
use App\Repository\SuividupreparationdujourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class RechercheCodeProduitController extends AbstractController
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

    #[Route('/recherche/code-produit', name: 'app_recherche_code_produit')]
    public function index(): Response
    {
        return $this->render('recherche_code_produit/index.html.twig');
    }

    #[Route('/recherche/code-produit/api', name: 'app_recherche_code_produit_api', methods: ['GET'])]
    public function rechercheApi(Request $request): JsonResponse
    {
        $codeProduit = $request->query->get('code_produit');
        $emplacementFiltre = $request->query->get('emplacement');

        if (!$codeProduit) {
            return $this->json(['error' => 'Code produit requis'], 400);
        }

        try {
            // 1. Recherche dans l'inventaire
            $qbInventaire = $this->inventaireRepository->createQueryBuilder('i')
                ->where('i.codeprod = :codeProduit')
                ->setParameter('codeProduit', $codeProduit)
                ->orderBy('i.emplacement', 'ASC');

            // Filtre par emplacement si spécifié
            if ($emplacementFiltre) {
                $qbInventaire->andWhere('i.emplacement = :emplacement')
                             ->setParameter('emplacement', $emplacementFiltre);
            }

            $produitsInventaire = $qbInventaire->getQuery()->getResult();

            // 2. Recherche dans le suivi (aujourd'hui uniquement)
            $aujourdhui = new \DateTime('today');
            $demain = new \DateTime('tomorrow');

            $qbSuivi = $this->suiviRepository->createQueryBuilder('s')
                ->where('s.CodeProduit = :codeProduit')
                ->andWhere('s.updatedAt >= :aujourdhui')
                ->andWhere('s.updatedAt < :demain')
                ->setParameter('codeProduit', $codeProduit)
                ->setParameter('aujourdhui', $aujourdhui)
                ->setParameter('demain', $demain)
                ->orderBy('s.Adresse', 'ASC')
                ->addOrderBy('s.updatedAt', 'DESC');

            // Filtre par emplacement si spécifié
            if ($emplacementFiltre) {
                $qbSuivi->andWhere('s.Adresse = :emplacement')
                        ->setParameter('emplacement', $emplacementFiltre);
            }

            $mouvementsSuivi = $qbSuivi->getQuery()->getResult();

            // 3. Calcul des totaux par emplacement
            $emplacementsData = [];
            $totalGeneralInventaire = 0;
            $totalGeneralSuivi = 0;

            // Traiter les données d'inventaire
            foreach ($produitsInventaire as $produit) {
                $emplacement = $produit->getEmplacement();
                
                if (!isset($emplacementsData[$emplacement])) {
                    $emplacementsData[$emplacement] = [
                        'emplacement' => $emplacement,
                        'zone' => $produit->getZone(),
                        'inventaire' => [
                            'uvTotal' => 0,
                            'produits' => []
                        ],
                        'suivi' => [
                            'nbArtTotal' => 0,
                            'nbArtEnAttente' => 0,
                            'nbArtEnCours' => 0,
                            'mouvements' => []
                        ]
                    ];
                }

                $emplacementsData[$emplacement]['inventaire']['uvTotal'] += $produit->getUvtotal();
                $emplacementsData[$emplacement]['inventaire']['produits'][] = [
                    'id' => $produit->getId(),
                    'codeProduit' => $produit->getCodeprod(),
                    'designation' => $produit->getDsignprod(),
                    'uvTotal' => $produit->getUvtotal(),
                    'nopalinfo' => $produit->getNopalinfo()
                ];

                $totalGeneralInventaire += $produit->getUvtotal();
            }

            // Traiter les données de suivi
            foreach ($mouvementsSuivi as $mouvement) {
                $emplacement = $mouvement->getAdresse();
                
                if (!isset($emplacementsData[$emplacement])) {
                    $emplacementsData[$emplacement] = [
                        'emplacement' => $emplacement,
                        'zone' => $mouvement->getZone(),
                        'inventaire' => [
                            'uvTotal' => 0,
                            'produits' => []
                        ],
                        'suivi' => [
                            'nbArtTotal' => 0,
                            'nbArtEnAttente' => 0,
                            'nbArtEnCours' => 0,
                            'mouvements' => []
                        ]
                    ];
                }

                $nbArt = $mouvement->getNbArt() ?? 0;
                $emplacementsData[$emplacement]['suivi']['nbArtTotal'] += $nbArt;
                $totalGeneralSuivi += $nbArt;

                // Calculer les statuts
                if ($mouvement->getFlasher() === null && $mouvement->getValider() === null) {
                    // En attente (pas flashé)
                    $emplacementsData[$emplacement]['suivi']['nbArtEnAttente'] += $nbArt;
                } elseif ($mouvement->getFlasher() === 'Ok' && $mouvement->getValider() === null) {
                    // En cours (flashé mais pas validé)
                    $emplacementsData[$emplacement]['suivi']['nbArtEnCours'] += $nbArt;
                }

                $emplacementsData[$emplacement]['suivi']['mouvements'][] = [
                    'id' => $mouvement->getId(),
                    'noPal' => $mouvement->getNoPal(),
                    'nbArt' => $nbArt,
                    'client' => $mouvement->getClient(),
                    'flasher' => $mouvement->getFlasher(),
                    'valider' => $mouvement->getValider() ? $mouvement->getValider()->format('Y-m-d H:i:s') : null,
                    'dateUpdate' => $mouvement->getUpdatedAt()?->format('d/m/Y H:i:s'),
                    'statut' => $this->determinerStatut($mouvement)
                ];
            }

            // 4. Obtenir la liste des emplacements pour le filtre
            $emplacementsDisponibles = $this->inventaireRepository->createQueryBuilder('i')
                ->select('DISTINCT i.emplacement')
                ->where('i.codeprod = :codeProduit')
                ->andWhere('i.emplacement IS NOT NULL')
                ->setParameter('codeProduit', $codeProduit)
                ->orderBy('i.emplacement', 'ASC')
                ->getQuery()
                ->getScalarResult();

            $emplacementsDisponibles = array_column($emplacementsDisponibles, 'emplacement');

            // 5. Statistiques générales
            $nombreEmplacements = count($emplacementsData);
            $stockLiveTotal = 0;

            foreach ($emplacementsData as &$data) {
                $stockLive = $data['inventaire']['uvTotal'] 
                           + $data['suivi']['nbArtEnAttente'] 
                           - $data['suivi']['nbArtEnCours'];
                $data['stockLive'] = $stockLive;
                $stockLiveTotal += $stockLive;
            }

            return $this->json([
                'codeProduit' => $codeProduit,
                'emplacementFiltre' => $emplacementFiltre,
                'dateRecherche' => $aujourdhui->format('d/m/Y'),
                'statistiques' => [
                    'nombreEmplacements' => $nombreEmplacements,
                    'totalInventaire' => $totalGeneralInventaire,
                    'totalSuivi' => $totalGeneralSuivi,
                    'stockLiveTotal' => $stockLiveTotal
                ],
                'emplacements' => array_values($emplacementsData),
                'emplacementsDisponibles' => $emplacementsDisponibles,
                'filtres' => [
                    'description' => 'Données du suivi limitées à aujourd\'hui uniquement',
                    'criteres' => [
                        'en_attente' => 'Flasher IS NULL ET valider IS NULL',
                        'en_cours' => 'Flasher = \'Ok\' ET valider IS NULL',
                        'traite' => 'valider IS NOT NULL'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la recherche: ' . $e->getMessage()
            ], 500);
        }
    }

    private function determinerStatut($mouvement): string
    {
        if ($mouvement->getValider() !== null) {
            return 'Traité';
        } elseif ($mouvement->getFlasher() === 'Ok') {
            return 'En cours';
        } elseif ($mouvement->getFlasher() === null) {
            return 'En attente';
        } else {
            return 'Autre (' . $mouvement->getFlasher() . ')';
        }
    }
}
    