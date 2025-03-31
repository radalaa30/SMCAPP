<?php
// src/Controller/SuiviPreparationHistoriqueController.php
namespace App\Controller;

use App\Entity\Suividupreparationdujour;
use App\Repository\SuividupreparationdujourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin')]
class SuiviPreparationHistoriqueController extends AbstractController
{
    private $repository;
    private $entityManager;
    private $paginator;

    public function __construct(
        SuividupreparationdujourRepository $repository,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
    ) {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
        $this->paginator = $paginator;
    }

    #[Route('/admin/historique/suivi-preparation', name: 'app_historique_suivi_preparation')]
    public function index(Request $request): Response
    {
        // Récupération des paramètres de filtrage
        $dateDebut = $request->query->get('dateDebut');
        $dateFin = $request->query->get('dateFin');
        $codeClient = $request->query->get('codeClient');
        $preparateur = $request->query->get('preparateur');
        $noBl = $request->query->get('noBl');
        $codeProduits = $request->query->get('codeProduits');
        $client = $request->query->get('client');

        // Construction de la requête
        $queryBuilder = $this->repository->createQueryBuilder('s')
            ->orderBy('s.updatedAt', 'DESC');

        // Ajout des filtres si présents
        if ($dateDebut) {
            $queryBuilder
                ->andWhere('s.updatedAt >= :dateDebut')
                ->setParameter('dateDebut', new \DateTime($dateDebut));
        }
        if ($dateFin) {
            $queryBuilder
                ->andWhere('s.updatedAt <= :dateFin')
                ->setParameter('dateFin', new \DateTime($dateFin));
        }
        if ($codeClient) {
            $queryBuilder
                ->andWhere('s.Code_Client LIKE :codeClient')
                ->setParameter('codeClient', '%' . $codeClient . '%');
        }
        if ($preparateur) {
            $queryBuilder
                ->andWhere('s.Preparateur LIKE :preparateur')
                ->setParameter('preparateur', '%' . $preparateur . '%');
        }
        if ($noBl) {
            $queryBuilder
                ->andWhere('s.No_Bl LIKE :noBl')
                ->setParameter('noBl', '%' . $noBl . '%');
        }

        // Filtre par codes produits (multiples)
        if ($codeProduits) {
            $codeProduitsArray = array_map('trim', explode(',', $codeProduits));
            $andX = $queryBuilder->expr()->andX();

            foreach ($codeProduitsArray as $key => $codeProduit) {
                $paramName = 'codeProduit_' . $key;
                $andX->add($queryBuilder->expr()->like('s.CodeProduit', ':' . $paramName));
                $queryBuilder->setParameter($paramName, '%' . $codeProduit . '%');
            }

            $queryBuilder->andWhere($andX);
        }

        // Filtre par client
        if ($client) {
            $queryBuilder
                ->andWhere('s.Client LIKE :client')
                ->setParameter('client', '%' . $client . '%');
        }

        // Pagination
        $pagination = $this->paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            25 // Nombre d'éléments par page
        );

        // Statistiques
        $stats = $this->getStatistics($dateDebut, $dateFin);

        return $this->render('suivi_preparation_histor/historique.html.twig', [
            'pagination' => $pagination,
            'stats' => $stats,
            'filtres' => [
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin,
                'codeClient' => $codeClient,
                'preparateur' => $preparateur,
                'noBl' => $noBl,
                'codeProduits' => $codeProduits,
                'client' => $client
            ]
        ]);
    }

    private function getStatistics(?string $dateDebut, ?string $dateFin): array
    {
        $queryBuilder = $this->repository->createQueryBuilder('s')
            ->select('COUNT(s.id) as total')
            ->addSelect('SUM(s.Nb_Pal) as totalPalettes')
            ->addSelect('COUNT(DISTINCT s.Preparateur) as totalPreparateurs')
            ->addSelect('SUM(s.Nb_art) as totalArticles')
            ->addSelect('SUM(s.Nb_col) as totalColis');

        if ($dateDebut) {
            $queryBuilder
                ->andWhere('s.updatedAt >= :dateDebut')
                ->setParameter('dateDebut', new \DateTime($dateDebut));
        }
        if ($dateFin) {
            $queryBuilder
                ->andWhere('s.updatedAt <= :dateFin')
                ->setParameter('dateFin', new \DateTime($dateFin));
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    #[Route('/historique/suivi-preparation/{id}', name: 'app_historique_suivi_preparation_detail')]
    public function detail(Suividupreparationdujour $suivi): Response
    {
        return $this->render('suivi/detail.html.twig', [
            'suivi' => $suivi
        ]);
    }

    #[Route('/historique/suivi-preparation/export', name: 'app_historique_suivi_preparation_export')]
    public function export(Request $request): Response
    {
        // Récupération des paramètres de filtrage
        $dateDebut = $request->query->get('dateDebut');
        $dateFin = $request->query->get('dateFin');
        $codeClient = $request->query->get('codeClient');
        $preparateur = $request->query->get('preparateur');
        $noBl = $request->query->get('noBl');
        $codeProduits = $request->query->get('codeProduits');
        $client = $request->query->get('client');

        // Construction de la requête
        $queryBuilder = $this->repository->createQueryBuilder('s')
            ->orderBy('s.updatedAt', 'DESC');

        // Application des filtres
        if ($dateDebut) {
            $queryBuilder
                ->andWhere('s.updatedAt >= :dateDebut')
                ->setParameter('dateDebut', new \DateTime($dateDebut));
        }
        if ($dateFin) {
            $queryBuilder
                ->andWhere('s.updatedAt <= :dateFin')
                ->setParameter('dateFin', new \DateTime($dateFin));
        }
        if ($codeClient) {
            $queryBuilder
                ->andWhere('s.Code_Client LIKE :codeClient')
                ->setParameter('codeClient', '%' . $codeClient . '%');
        }
        if ($preparateur) {
            $queryBuilder
                ->andWhere('s.Preparateur LIKE :preparateur')
                ->setParameter('preparateur', '%' . $preparateur . '%');
        }
        if ($noBl) {
            $queryBuilder
                ->andWhere('s.No_Bl LIKE :noBl')
                ->setParameter('noBl', '%' . $noBl . '%');
        }
        if ($codeProduits) {
            $codeProduitsArray = array_map('trim', explode(',', $codeProduits));
            $orX = $queryBuilder->expr()->orX();

            foreach ($codeProduitsArray as $key => $codeProduit) {
                $paramName = 'codeProduit_' . $key;
                $orX->add($queryBuilder->expr()->like('s.CodeProduit', ':' . $paramName));
                $queryBuilder->setParameter($paramName, '%' . $codeProduit . '%');
            }

            $queryBuilder->andWhere($orX);
        }
        if ($client) {
            $queryBuilder
                ->andWhere('s.Client LIKE :client')
                ->setParameter('client', '%' . $client . '%');
        }

        $data = $queryBuilder->getQuery()->getResult();

        // Création du fichier CSV
        $csv = fopen('php://temp', 'r+');

        // En-têtes
        fputcsv($csv, [
            'Date',
            'Ref',
            'Code Client',
            'Client',
            'Préparateur',
            'No BL',
            'No Commande',
            'Statut',
            'Zone',
            'Nb Palettes',
            'Nb Colis',
            'Nb Articles',
            'Date Livraison',
            'Transporteur'
        ]);

        // Données
        foreach ($data as $row) {
            fputcsv($csv, [
                $row->getUpdatedAt()?->format('Y-m-d H:i:s'),
                $row->getCodeClient(),
                $row->getCodeProduit(),
                $row->getClient(),
                $row->getPreparateur(),
                $row->getNoBl(),
                $row->getNoCmd(),
                $row->getStatutCde(),
                $row->getZone(),
                $row->getNbPal(),
                $row->getNbCol(),
                $row->getNbArt(),
                $row->getDateLiv()?->format('Y-m-d'),
                $row->getTransporteur()
            ]);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="historique-suivi-preparation.csv"');

        return $response;
    }
}
