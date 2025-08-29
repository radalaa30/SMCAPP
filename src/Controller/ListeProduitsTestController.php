<?php

namespace App\Controller;

use App\Entity\ListeProduits;
use App\Repository\ListeProduitsRepository;
use App\Repository\SuividupreparationdujourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class ListeProduitsTestController extends AbstractController
{
    // Deux URLs vers la même page (garde celles que tu utilises)
    #[Route('/liste/produits', name: 'app_liste_produits_index')]
    #[Route('/liste/produits/test', name: 'app_liste_produits_test')]
    public function index(Request $request, ListeProduitsRepository $repo): Response
    {
        $ref   = trim((string) $request->query->get('ref', ''));
        $des   = trim((string) $request->query->get('des', ''));
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(200, max(5, (int) $request->query->get('limit', 20)));
        $sort  = (string) $request->query->get('sort', 'ref');
        $dir   = (string) $request->query->get('dir', 'ASC');

        // Ton repo doit avoir une méthode search($ref,$des,$page,$limit,$sort,$dir)
        [$produits, $total] = $repo->search($ref, $des, $page, $limit, $sort, $dir);
        $pages = (int) ceil($total / $limit);

        return $this->render('liste_produits_test/index.html.twig', [
            'produits' => $produits,
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'limit'    => $limit,
            'sort'     => $sort,
            'dir'      => $dir,
            'f_ref'    => $ref,
            'f_des'    => $des,
        ]);
    }

    #[Route('/liste/produits/{id}', name: 'app_liste_produits_show', requirements: ['id' => '\d+'])]
    public function show(
        ListeProduits $produit,
        Request $request,
        SuividupreparationdujourRepository $suiviRepo
    ): Response {
        // Pagination des suivis (bloc du bas)
        $spage  = max(1, (int) $request->query->get('spage', 1));
        $slimit = min(200, max(10, (int) $request->query->get('slimit', 20)));

        // Filtres (GET). Les champs "date" arrivent en 'Y-m-d'.
        $f = [
            'noBl'         => trim((string) $request->query->get('f_noBl', '')),
            'noCmd'        => trim((string) $request->query->get('f_noCmd', '')),
            'client'       => trim((string) $request->query->get('f_client', '')),
            'codeClient'   => trim((string) $request->query->get('f_codeClient', '')),
            'zone'         => trim((string) $request->query->get('f_zone', '')),
            'adresse'      => trim((string) $request->query->get('f_adresse', '')),
            'flasher'      => trim((string) $request->query->get('f_flasher', '')),
            'preparateur'  => trim((string) $request->query->get('f_preparateur', '')),
            'transporteur' => trim((string) $request->query->get('f_transporteur', '')),
            'maj_from_raw' => trim((string) $request->query->get('f_maj_from', '')),
            'maj_to_raw'   => trim((string) $request->query->get('f_maj_to', '')),
            'liv_from_raw' => trim((string) $request->query->get('f_liv_from', '')),
            'liv_to_raw'   => trim((string) $request->query->get('f_liv_to', '')),
        ];

        // Parsing dates en objets (début/fin de journée)
        $majFrom = $f['maj_from_raw'] ? new \DateTimeImmutable($f['maj_from_raw'].' 00:00:00') : null;
        $majTo   = $f['maj_to_raw']   ? new \DateTimeImmutable($f['maj_to_raw'].' 23:59:59')   : null;
        $livFrom = $f['liv_from_raw'] ? new \DateTimeImmutable($f['liv_from_raw'].' 00:00:00') : null;
        $livTo   = $f['liv_to_raw']   ? new \DateTimeImmutable($f['liv_to_raw'].' 23:59:59')   : null;

        $filters = [
            'noBl'         => $f['noBl'],
            'noCmd'        => $f['noCmd'],
            'client'       => $f['client'],
            'codeClient'   => $f['codeClient'],
            'zone'         => $f['zone'],
            'adresse'      => $f['adresse'],
            'flasher'      => $f['flasher'],
            'preparateur'  => $f['preparateur'],
            'transporteur' => $f['transporteur'],
            'maj_from'     => $majFrom,
            'maj_to'       => $majTo,
            'liv_from'     => $livFrom,
            'liv_to'       => $livTo,
        ];

        $ref = (string) $produit->getRef(); // Jointure logique : CodeProduit == ref
        [$suivis, $stotal] = $suiviRepo->findByCodeProduitFilteredPaginated($ref, $filters, $spage, $slimit);
        $spages = (int) ceil($stotal / $slimit);

        return $this->render('liste_produits_test/show.html.twig', [
            'produit' => $produit,
            'suivis'  => $suivis,
            'stotal'  => $stotal,
            'spage'   => $spage,
            'spages'  => $spages,
            'slimit'  => $slimit,
            'f'       => $f,   // pour réafficher les champs
        ]);
    }
}
