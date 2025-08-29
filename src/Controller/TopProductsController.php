<?php

namespace App\Controller;

use App\Repository\SuiviProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/top-produits')]
class TopProductsController extends AbstractController
{
    public function __construct(private SuiviProductRepository $repo) {}

    #[Route('', name: 'app_top_products', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Période par défaut = 3 derniers mois
        $end   = new \DateTimeImmutable('today 23:59:59');
        $start = $end->sub(new \DateInterval('P3M'))->setTime(0, 0, 0);

        // Surcharges via query string ?start=YYYY-mm-dd&end=YYYY-mm-dd
        $startQ = $request->query->get('start');
        $endQ   = $request->query->get('end');
        if ($startQ) {
            $tmp = \DateTimeImmutable::createFromFormat('Y-m-d', $startQ);
            if ($tmp) $start = $tmp->setTime(0,0,0);
        }
        if ($endQ) {
            $tmp = \DateTimeImmutable::createFromFormat('Y-m-d', $endQ);
            if ($tmp) $end = $tmp->setTime(23,59,59);
        }

        $sort  = in_array($request->query->get('sort'), ['lines', 'qty'], true) ? $request->query->get('sort') : 'lines';
        $limit = (int)($request->query->get('limit') ?? 50);
        if ($limit < 1) { $limit = 50; }

        $rows   = $this->repo->findTopProducts($start, $end, $sort, $limit);
        $totals = $this->repo->totals($start, $end);

        // Données graphe
        $labels = [];
        $serieLines = [];
        $serieQty   = [];
        foreach ($rows as $r) {
            $labels[]     = (string)$r['code_produit'];
            $serieLines[] = (int)$r['lignes'];
            $serieQty[]   = (int)$r['total_nb_art'];
        }

        return $this->render('coldhot/top_products.html.twig', [
            'start'        => $start,
            'end'          => $end,
            'sort'         => $sort,
            'limit'        => $limit,
            'rows'         => $rows,
            'totals'       => $totals,
            'chartLabels'  => $labels,
            'chartLines'   => $serieLines,
            'chartQty'     => $serieQty,
        ]);
    }

    #[Route('/distribution/{code}', name: 'app_top_products_distribution', methods: ['GET'])]
    public function distribution(string $code, Request $request): JsonResponse
    {
        // Période identique à la page principale
        $end   = new \DateTimeImmutable('today 23:59:59');
        $start = $end->sub(new \DateInterval('P3M'))->setTime(0, 0, 0);

        $startQ = $request->query->get('start');
        $endQ   = $request->query->get('end');
        if ($startQ) {
            $tmp = \DateTimeImmutable::createFromFormat('Y-m-d', $startQ);
            if ($tmp) $start = $tmp->setTime(0,0,0);
        }
        if ($endQ) {
            $tmp = \DateTimeImmutable::createFromFormat('Y-m-d', $endQ);
            if ($tmp) $end = $tmp->setTime(23,59,59);
        }

        $dist = $this->repo->findDistributionByProduct($code, $start, $end);

        return $this->json([
            'code'  => $code,
            'start' => $start->format('Y-m-d'),
            'end'   => $end->format('Y-m-d'),
            'data'  => $dist,
        ]);
    }
}
