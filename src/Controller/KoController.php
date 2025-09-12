<?php

namespace App\Controller;

use App\Entity\KoSuivi;
use App\Repository\KoSuiviRepository;
use App\Repository\SuividupreparationdujourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('admin/ko', name: 'ko_')]
class KoController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        SuividupreparationdujourRepository $repo
    ): Response {
        $filters = [
            'codeProduit' => $request->query->get('codeProduit'),
            'client' => $request->query->get('client'),
            'preparateur' => $request->query->get('preparateur'),
            'dateFrom' => $request->query->get('dateFrom') ? new \DateTime($request->query->get('dateFrom')) : null,
            'dateTo' => $request->query->get('dateTo') ? new \DateTime($request->query->get('dateTo')) : null,
        ];
        $page  = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 25);

        $data = $repo->paginateKoWithLast($filters, $page, $limit);

        return $this->render('ko/index.html.twig', [
            'items'   => $data['items'],
            'last'    => $data['last'],
            'total'   => $data['total'],
            'page'    => $page,
            'limit'   => $limit,
            'filters' => $filters,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        int $id,
        SuividupreparationdujourRepository $repo,
        KoSuiviRepository $koRepo   // ✅ on injecte le repo ici
    ): Response {
        $row = $repo->find($id);
        if (!$row) {
            throw $this->createNotFoundException('Enregistrement introuvable.');
        }

        // ✅ plus de getDoctrine() — on utilise le repo injecté
        $historique = $koRepo->findBy(['suivi' => $row], ['createdAt' => 'DESC']);

        return $this->render('ko/show.html.twig', [
            'row'        => $row,
            'historique' => $historique,
            'statuts'    => KoSuivi::STATUTS,
        ]);
    }

    #[Route('/{id}/traiter', name: 'traiter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function traiter(
        int $id,
        Request $request,
        SuividupreparationdujourRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $row = $repo->find($id);
        if (!$row) {
            throw $this->createNotFoundException('Enregistrement introuvable.');
        }

        $statut      = (string) $request->request->get('statut', 'NOUVEAU');
        $cause       = $request->request->get('cause') ?: null;
        $commentaire = $request->request->get('commentaire') ?: null;
        $traite      = (bool) $request->request->get('traite', false);

        if (!in_array(strtoupper($statut), KoSuivi::STATUTS, true)) {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirectToRoute('ko_show', ['id' => $id]);
        }

        $entry = (new KoSuivi())
            ->setSuivi($row)
            ->setStatut($statut)
            ->setTraite($traite)
            ->setCause($cause)
            ->setCommentaire($commentaire)
            ->setAuteur($this->getUser() ? (string) $this->getUser()->getUserIdentifier() : 'system');

        $em->persist($entry);
        $em->flush();

        $this->addFlash('success', 'Traitement enregistré.');
        return $this->redirectToRoute('ko_show', ['id' => $id]);
    }

    #[Route('/export.csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(
        Request $request,
        SuividupreparationdujourRepository $repo
    ): Response {
        $filters = [
            'codeProduit' => $request->query->get('codeProduit'),
            'client' => $request->query->get('client'),
            'preparateur' => $request->query->get('preparateur'),
            'dateFrom' => $request->query->get('dateFrom') ? new \DateTime($request->query->get('dateFrom')) : null,
            'dateTo' => $request->query->get('dateTo') ? new \DateTime($request->query->get('dateTo')) : null,
        ];

        $data = $repo->exportKoWithLast($filters, 10000);
        $rows = $data['items'];
        $last = $data['last'];

        $response = new StreamedResponse(function () use ($rows, $last) {
            $h = fopen('php://output', 'w');
            fputcsv($h, [
                'ID','CodeProduit','Client','No_Bl','Preparateur','No_Pal','Zone','Adresse','Nb_art',
                'No_Cmd','Code_Client','MAJ','DernierStatut','Traite','Cause','DerniereMajTraitement'
            ], ';');

            foreach ($rows as $r) {
                /** @var \App\Entity\Suividupreparationdujour $r */
                $k = $last[$r->getId()] ?? null;
                fputcsv($h, [
                    $r->getId(),
                    $r->getCodeProduit(),
                    $r->getClient(),
                    $r->getNoBl(),
                    $r->getPreparateur(),
                    $r->getNoPal(),
                    $r->getZone(),
                    $r->getAdresse(),
                    $r->getNbArt(),
                    $r->getNoCmd(),
                    $r->getCodeClient(),
                    $r->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    $k?->getStatut() ?? '',
                    $k?->isTraite() ? 'Oui' : 'Non',
                    $k?->getCause() ?? '',
                    $k?->getCreatedAt()?->format('Y-m-d H:i:s') ?? '',
                ], ';');
            }
            fclose($h);
        });

        $filename = 'ko_export_'.date('Ymd_His').'.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
