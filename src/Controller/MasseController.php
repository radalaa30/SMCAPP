<?php

namespace App\Controller;

use App\Repository\InventairecompletRepository;
use App\Repository\ProblemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MasseController extends AbstractController
{
    #[Route('admin/masse', name: 'app_masse')]
    public function index(
        InventairecompletRepository $inventaireRepo,
        ProblemeRepository $problemeRepo
    ): Response {
        /**
         * ==== Bornes/logique MASSERIE (sans niveaux) ====
         * A (impair) : 095→123, EXTINCTEUR, 201→223 (impairs)
         * A (pair)   : 094→114, PASSAGE,    200→228 (pairs)
         * B (impair) : 095→115, PASSAGE,    201→229 (impairs)
         * B (pair)   : 094→122, EXTINCTEUR, 200→222 (pairs)
         * 👉 Si tu veux 2023 sur A impair, remplace 223 par 2023 (et laisse la regex côté repo, elle gère 3/4 chiffres).
         */
        $ranges = [
            'A_impair' => [['start' => 95,  'end' => 123, 'odd' => true],
                           ['start' => 201, 'end' => 223, 'odd' => true]],
            'A_pair'   => [['start' => 94,  'end' => 114, 'odd' => false],
                           ['start' => 200, 'end' => 228, 'odd' => false]],
            'B_impair' => [['start' => 95,  'end' => 115, 'odd' => true],
                           ['start' => 201, 'end' => 229, 'odd' => true]],
            'B_pair'   => [['start' => 94,  'end' => 122, 'odd' => false],
                           ['start' => 200, 'end' => 222, 'odd' => false]],
        ];

        $pad3 = static fn (int $n): string => str_pad((string)$n, 3, '0', STR_PAD_LEFT);

        // Construit toutes les bases (adresses “masse” sans niveau)
        $allBases = [];
        $addSlice = static function (string $allee, array $slice) use (&$allBases, $pad3) {
            for ($n = $slice['start']; $n <= $slice['end']; $n++) {
                if ($slice['odd'] && $n % 2 === 0)  continue;
                if (!$slice['odd'] && $n % 2 !== 0) continue;
                $allBases[] = "M1_{$allee}_" . $pad3($n);
            }
        };
        foreach ($ranges['A_impair'] as $s) $addSlice('A', $s);
        foreach ($ranges['A_pair']   as $s) $addSlice('A', $s);
        foreach ($ranges['B_impair'] as $s) $addSlice('B', $s);
        foreach ($ranges['B_pair']   as $s) $addSlice('B', $s);

        $allBases    = array_values(array_unique($allBases));
        $allBasesSet = array_flip($allBases);

        // ====== Initialisation (1 case par adresse) ======
        $emplacements = [];
        foreach ($allBases as $base) {
            $emplacements[$base] = [
                'id'          => null,
                'code'        => $base,
                'plein'       => false,
                'rouge'       => false,
                'enSortie'    => false,
                'multiple'    => false,   // plusieurs palettes
                'multiRef'    => false,   // plusieurs références à la même adresse
                'count'       => 0,
                'nopalinfo'   => [],
                'codeprod'    => [],
                'designation' => [],
                'ucdispo'     => 0,
                'urdispo'     => 0,
                'uvtotal'     => 0,
                'uvensortie'  => 0,
                'probleme'    => false,
            ];
        }

        // ====== Problèmes actifs ======
        // On marque la base si un problème pointe sur "M1_X_YYY" ou "M1_X_YYY-<niveau>"
        $problemes = $problemeRepo->findActiveProblems();
        foreach ($problemes as $p) {
            $emp = (string)$p->getEmplacement();
            $base = strstr($emp, '-', true) ?: $emp;
            if (isset($allBasesSet[$base])) {
                $emplacements[$base]['probleme'] = true;
            }
        }

        // ====== Charge l’inventaire pour ces adresses (toutes lignes “-niveau” incluses) ======
        $inventaires = $inventaireRepo->findByBasePrefixesIn($allBases);

        // Agrégation par adresse de base (on ignore le niveau)
        foreach ($inventaires as $row) {
            $empFull = (string)$row->getEmplacement();       // "M1_A_095" ou "M1_A_095-2"
            $base    = strstr($empFull, '-', true) ?: $empFull;
            if (!isset($allBasesSet[$base])) {
                continue; // hors périmètre de la masse
            }

            $cur   = $emplacements[$base];
            $count = $cur['count'] + 1;

            // Flags “état”
            $isPlein    = ((int)$row->getUrdispo() > 0 || (int)$row->getUcdispo() > 0);
            $isRouge    = ((int)$row->getUrbloquee() > 0);
            $isEnSortie = ((int)$row->getUvensortie() > 0);

            $codesProdAvant = $cur['codeprod'];
            $codesProdApres = array_merge($codesProdAvant, [(string)$row->getCodeprod()]);
            $multiRef = (count(array_unique($codesProdApres)) > 1); // plusieurs refs différentes

            $emplacements[$base] = [
                'id'          => $cur['id'] ?? $row->getId(),
                'code'        => $base,
                'plein'       => $isPlein    || $cur['plein'],
                'rouge'       => $isRouge    || $cur['rouge'],
                'enSortie'    => $isEnSortie || $cur['enSortie'],
                'multiple'    => ($count > 1),
                'multiRef'    => $multiRef || $cur['multiRef'],
                'count'       => $count,
                'nopalinfo'   => array_merge($cur['nopalinfo'],   [(string)$row->getNopalinfo()]),
                'codeprod'    => $codesProdApres,
                'designation' => array_merge($cur['designation'], [(string)$row->getDsignprod()]),
                'ucdispo'     => (int)$cur['ucdispo']    + (int)$row->getUcdispo(),
                'urdispo'     => (int)$cur['urdispo']    + (int)$row->getUrdispo(),
                'uvtotal'     => (int)$cur['uvtotal']    + (int)$row->getUvtotal(),
                'uvensortie'  => (int)$cur['uvensortie'] + (int)$row->getUvensortie(),
                'probleme'    => $cur['probleme'],
            ];
        }

        ksort($emplacements);

        return $this->render('emplacement/masse.html.twig', [
            'emplacements' => $emplacements,
        ]);
    }
}
