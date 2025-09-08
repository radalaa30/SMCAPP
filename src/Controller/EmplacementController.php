<?php

namespace App\Controller;

use App\Entity\Probleme;
use App\Repository\InventairecompletRepository;
use App\Repository\ProblemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_CONSULTATION')]

class EmplacementController extends AbstractController
{
    #[Route('/emplacements', name: 'app_emplacements')]
    public function index(
        Request $request,
        InventairecompletRepository $repository,
        ProblemeRepository $problemeRepository
    ): Response {
        $this->denyAccessUnlessGranted('ADMIN');
        // Normalisation des paramètres
        $cellule = strtoupper((string) $request->query->get('cellule', 'C2'));
        $allee   = strtoupper((string) $request->query->get('allee', 'A'));


        // Source de vérité : cellules et allées disponibles
        $cellules = [
            'C2' => ['allees' => ['A','B','C','D','E','F','G','H']],
            'C3' => ['allees' => ['A','B','C','D','E','F','G','H']],
            // ✅ C4 inclut désormais F, G, H
            'C4' => ['allees' => ['A','B','C','D','F','G','H']],
        ];

        // Validation douce
        if (!array_key_exists($cellule, $cellules)) {
            $cellule = 'C2';
        }
        if (!in_array($allee, $cellules[$cellule]['allees'], true)) {
            $allee = $cellules[$cellule]['allees'][0];
        }

        // Données BDD pour la cellule/allée demandée
        $emplacements = $repository->findEmplacementsByCelluleAndAllee($cellule, $allee);

        // Problèmes actifs indexés par emplacement
        $problemes = $problemeRepository->findActiveProblems();
        $problemesParEmplacement = [];
        foreach ($problemes as $p) {
            if (str_starts_with($p->getEmplacement(), $cellule . ':' . $allee . '-')) {
                $problemesParEmplacement[$p->getEmplacement()] = true;
            }
        }

        // Grille d’affichage
        $emplacementsData = [
            'gauche' => [], // positions impaires
            'droite' => [], // positions paires
        ];

        // Bornes par défaut
        $startImpair = 1;   $endImpair = 151;
        $startPair   = 2;   $endPair   = 152;

        // Spécificités C2 (inchangé)
        if ($cellule === 'C2') {
            if ($allee === 'B') {
                $startImpair = 25;
            } elseif (in_array($allee, ['C','D','E','F','G','H'], true)) {
                $startImpair = 25;
                $startPair   = 26;
            }
        }

        // Initialise les cases vides
        for ($position = $startImpair; $position <= $endImpair; $position += 2) {
            for ($niveau = 0; $niveau <= 4; $niveau++) {
                $code = sprintf('%s:%s-%02d-%02d', $cellule, $allee, $position, $niveau);
                $emplacementsData['gauche'][$position][$niveau] = [
                    'id' => null,
                    'code' => $code,
                    'plein' => false,
                    'rouge' => false,
                    'enSortie' => false,
                    'multiple' => false,
                    'count' => 0,
                    'nopalinfo' => [],      // <-- numéro palette
                    'codeprod' => [],
                    'designation' => [],
                    'ucdispo' => 0,
                    'urdispo' => 0,
                    'uvtotal' => 0,
                    'uvensortie' => 0,
                    'probleme' => isset($problemesParEmplacement[$code]),
                ];
            }
        }
        for ($position = $startPair; $position <= $endPair; $position += 2) {
            for ($niveau = 0; $niveau <= 4; $niveau++) {
                $code = sprintf('%s:%s-%02d-%02d', $cellule, $allee, $position, $niveau);
                $emplacementsData['droite'][$position][$niveau] = [
                    'id' => null,
                    'code' => $code,
                    'plein' => false,
                    'rouge' => false,
                    'enSortie' => false,
                    'multiple' => false,
                    'count' => 0,
                    'nopalinfo' => [],      // <-- numéro palette
                    'codeprod' => [],
                    'designation' => [],
                    'ucdispo' => 0,
                    'urdispo' => 0,
                    'uvtotal' => 0,
                    'uvensortie' => 0,
                    'probleme' => isset($problemesParEmplacement[$code]),
                ];
            }
        }

        // Remplissage avec la BDD
        $re = '/([A-Z]\d+):([A-Z])-(\d+)-(\d+)/'; // ex: C4:F-10-2
        foreach ($emplacements as $e) {
            if (!preg_match($re, $e->getEmplacement(), $m)) {
                continue;
            }
            $position = (int) $m[3];
            $niveau   = (int) $m[4];
            $cote     = ($position % 2 === 0) ? 'droite' : 'gauche';

            if (!isset($emplacementsData[$cote][$position][$niveau])) {
                continue; // hors bornes
            }

            $cur   = $emplacementsData[$cote][$position][$niveau];
            $count = $cur['count'] + 1;

            $isPlein    = ($e->getUrdispo() > 0 || $e->getUcdispo() > 0);
            $isRouge    = ($e->getUrbloquee() > 0);
            $isEnSortie = ($e->getUvensortie() > 0);

            $emplacementsData[$cote][$position][$niveau] = [
                'id'          => $e->getId(),
                'code'        => $e->getEmplacement(),
                'plein'       => $isPlein,
                'rouge'       => $isRouge || $cur['rouge'],
                'enSortie'    => $isEnSortie || $cur['enSortie'],
                'multiple'    => $count > 1,
                'count'       => $count,
                'nopalinfo'   => array_merge($cur['nopalinfo'], [$e->getNopalinfo()]),
                'codeprod'    => array_merge($cur['codeprod'],    [$e->getCodeprod()]),
                'designation' => array_merge($cur['designation'], [$e->getDsignprod()]),
                'ucdispo'     => $cur['ucdispo']    + $e->getUcdispo(),
                'urdispo'     => $cur['urdispo']    + $e->getUrdispo(),
                'uvtotal'     => $cur['uvtotal']    + $e->getUvtotal(),
                'uvensortie'  => $cur['uvensortie'] + $e->getUvensortie(),
                'probleme'    => $cur['probleme'],
            ];
        }

        // Tri par position
        foreach (['gauche', 'droite'] as $cote) {
            ksort($emplacementsData[$cote]);
        }

        return $this->render('emplacement/index.html.twig', [
            'cellules'            => $cellules,
            'celluleSelectionnee' => $cellule,
            'alleeSelectionnee'   => $allee,
            'emplacements'        => $emplacementsData,
        ]);
    }

    #[Route('/probleme/signaler', name: 'app_probleme_signaler', methods: ['POST'])]
    public function signalerProbleme(
        Request $request,
        EntityManagerInterface $em,
        InventairecompletRepository $inventaireRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $emplacement = (string) ($data['emplacement'] ?? '');
        $description = (string) ($data['description'] ?? '');

        if ($emplacement === '' || $description === '') {
            return new JsonResponse(['success' => false, 'error' => 'Données incomplètes'], 400);
        }

        // Palettes à cet emplacement
        $inventaires = $inventaireRepo->findBy(['emplacement' => $emplacement]);

        if (empty($inventaires)) {
            // Problème générique (emplacement vide)
            $probleme = new Probleme();
            $probleme->setEmplacement($emplacement);
            $probleme->setNopalinfo('N/A');
            $probleme->setCodeprod('N/A');
            $probleme->setDsignprod('Emplacement vide');
            $probleme->setDescription($description);
            $probleme->setStatut('nouveau');
            $probleme->setDateSignalement(new \DateTime());
            $probleme->setUvtotal(0);
            $probleme->setUcdispo(0);
            $probleme->setUrdispo(0);
            $probleme->setUvensortie(0);
            $probleme->setUrbloquee(0);
            $probleme->setZone('N/A');

            $em->persist($probleme);
        } else {
            // Un problème par palette trouvée
            foreach ($inventaires as $inv) {
                $probleme = new Probleme();
                $probleme->setEmplacement($emplacement);
                $probleme->setNopalinfo($inv->getNopalinfo());
                $probleme->setCodeprod($inv->getCodeprod());
                $probleme->setDsignprod($inv->getDsignprod());
                $probleme->setDescription($description);
                $probleme->setStatut('nouveau');
                $probleme->setDateSignalement(new \DateTime());
                $probleme->setUvtotal($inv->getUvtotal());
                $probleme->setUcdispo($inv->getUcdispo());
                $probleme->setUrdispo($inv->getUrdispo());
                $probleme->setUvensortie($inv->getUvensortie());
                $probleme->setUrbloquee($inv->getUrbloquee());
                $probleme->setZone($inv->getZone());

                $infosPalette = [
                    'nopalinfo'          => $inv->getNopalinfo(),
                    'emplacementdoublon' => $inv->getEmplacementdoublon(),
                    'dateentree'         => $inv->getDateentree() ? $inv->getDateentree()->format('Y-m-d') : null,
                ];
                $probleme->setInfosPalette($infosPalette);

                $em->persist($probleme);
            }
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/problemes', name: 'app_problemes_liste')]
    public function listProblemes(ProblemeRepository $repository): Response
    {
        $problemes = $repository->findAll();
        $stats     = $repository->countByStatut();

        return $this->render('emplacement/problemes.html.twig', [
            'problemes' => $problemes,
            'stats'     => $stats,
        ]);
    }

    #[Route('/probleme/{id}/commenter', name: 'app_probleme_commenter', methods: ['POST'])]
    public function commenterProbleme(
        Probleme $probleme,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $commentaire = (string) ($data['commentaire'] ?? '');

        if ($commentaire === '') {
            return new JsonResponse(['success' => false, 'error' => 'Commentaire vide'], 400);
        }

        $probleme->setCommentaire($commentaire);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/probleme/{id}/resoudre', name: 'app_probleme_resoudre', methods: ['POST'])]
    public function resoudreProbleme(
        Probleme $probleme,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $commentaire = isset($data['commentaire']) ? (string) $data['commentaire'] : null;

        $probleme->setStatut('resolu');
        $probleme->setDateResolution(new \DateTime());

        if ($commentaire) {
            $probleme->setCommentaire($commentaire);
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/probleme/{id}/supprimer', name: 'app_probleme_supprimer', methods: ['POST'])]
    public function supprimerProbleme(
        Probleme $probleme,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        // Récupère le token CSRF depuis le JSON
        $data = json_decode($request->getContent(), true) ?? [];
        $token = (string) ($data['_token'] ?? '');

        if (!$this->isCsrfTokenValid('delete_probleme_' . $probleme->getId(), $token)) {
            return new JsonResponse(['success' => false, 'error' => 'Token CSRF invalide'], 403);
        }

        $em->remove($probleme);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
