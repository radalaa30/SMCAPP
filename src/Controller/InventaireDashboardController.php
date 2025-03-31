<?php

namespace App\Controller;

use App\Entity\Inventairecomplet;
use App\Entity\Suividupreparationdujour;
use App\Repository\InventairecompletRepository;
use App\Repository\SuividupreparationdujourRepository;
use App\Service\InventaireService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventaireDashboardController extends AbstractController
{
    private $inventaireService;
    private $inventaireRepository;
    private $suiviRepository;
    private $entityManager;

    public function __construct(
        InventaireService $inventaireService,
        InventairecompletRepository $inventaireRepository,
        SuividupreparationdujourRepository $suiviRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->inventaireService = $inventaireService;
        $this->inventaireRepository = $inventaireRepository;
        $this->suiviRepository = $suiviRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/dashboard/inventaire', name: 'app_dashboard_inventaire')]
    public function index(Request $request): Response
    {
        // Critères de recherche
        $codeProduit = $request->query->get('codeProduit');
        $zone = $request->query->get('zone');
        $emplacement = $request->query->get('emplacement');

        // Récupération des données d'inventaire
        $qb = $this->inventaireRepository->createQueryBuilder('i');
        
        if ($codeProduit) {
            $qb->andWhere('i.codeprod LIKE :codeProduit')
               ->setParameter('codeProduit', '%' . $codeProduit . '%');
        }
        
        if ($zone) {
            $qb->andWhere('i.zone = :zone')
               ->setParameter('zone', $zone);
        }
        
        if ($emplacement) {
            $qb->andWhere('i.emplacement LIKE :emplacement')
               ->setParameter('emplacement', '%' . $emplacement . '%');
        }

        $inventaires = $qb->orderBy('i.codeprod', 'ASC')
                         ->setMaxResults(50)
                         ->getQuery()
                         ->getResult();

        // Récupération des zones pour le filtre
        $zones = $this->inventaireRepository->createQueryBuilder('i')
                     ->select('DISTINCT i.zone')
                     ->orderBy('i.zone', 'ASC')
                     ->getQuery()
                     ->getResult();

        // Statistiques globales
        $statsGlobales = [
            'totalProduits' => $this->inventaireRepository->count([]),
            'totalUvDisponibles' => $this->inventaireRepository->createQueryBuilder('i')
                                       ->select('SUM(i.uvtotal - i.uvensortie)')
                                       ->getQuery()
                                       ->getSingleScalarResult(),
            'produitsEnRupture' => $this->inventaireRepository->createQueryBuilder('i')
                                      ->select('COUNT(i.id)')
                                      ->where('i.uvtotal <= i.uvensortie')
                                      ->getQuery()
                                      ->getSingleScalarResult()
        ];

        return $this->render('inventaire_dashboard/index.html.twig', [
            'inventaires' => $inventaires,
            'zones' => $zones,
            'statsGlobales' => $statsGlobales,
            'filtres' => [
                'codeProduit' => $codeProduit,
                'zone' => $zone,
                'emplacement' => $emplacement
            ]
        ]);
    }

    #[Route('/dashboard/inventaire/mise-a-jour', name: 'app_dashboard_inventaire_update')]
    public function miseAJour(): Response
    {
        $nombreMisAJour = $this->inventaireService->mettreAJourUvensortie();
        
        $this->addFlash('success', "$nombreMisAJour enregistrements d'inventaire mis à jour");
        
        return $this->redirectToRoute('app_dashboard_inventaire');
    }

    #[Route('/dashboard/inventaire/detail/{id}', name: 'app_dashboard_inventaire_detail')]
    public function detail(Inventairecomplet $inventaire): Response
    {
        // Récupérer les mouvements associés à ce produit et cette adresse
        $mouvements = $this->suiviRepository->createQueryBuilder('s')
                          ->where('s.CodeProduit = :codeProduit')
                          ->andWhere('s.Adresse = :adresse')
                          ->setParameter('codeProduit', $inventaire->getCodeprod())
                          ->setParameter('adresse', $inventaire->getEmplacement())
                          ->orderBy('s.updatedAt', 'DESC')
                          ->setMaxResults(20)
                          ->getQuery()
                          ->getResult();

        return $this->render('inventaire_dashboard/detail.html.twig', [
            'inventaire' => $inventaire,
            'mouvements' => $mouvements,
            'quantiteReelle' => $inventaire->getUvtotal() - $inventaire->getUvensortie()
        ]);
    }
}