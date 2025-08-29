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

class EmplacementController extends AbstractController
{
    #[Route('/emplacements', name: 'app_emplacements')]
    public function index(
        Request $request, 
        InventairecompletRepository $repository,
        ProblemeRepository $problemeRepository
    ): Response {
        $cellule = $request->query->get('cellule', 'C2');
        $allee = $request->query->get('allee', 'A');
        
        // Liste des cellules et allées disponibles
        $cellules = [
            'C2' => ['allees' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']],
            'C3' => ['allees' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']],
            'C4' => ['allees' => ['A', 'B', 'C', 'D']]
        ];
        
        // Validation des paramètres
        if (!array_key_exists($cellule, $cellules)) {
            $cellule = 'C2';
        }
        
        if (!in_array($allee, $cellules[$cellule]['allees'])) {
            $allee = $cellules[$cellule]['allees'][0];
        }
        
        // Récupérer les données pour la cellule et l'allée sélectionnées
        $emplacements = $repository->findEmplacementsByCelluleAndAllee($cellule, $allee);
        
        // Récupérer les problèmes actifs pour cette cellule et allée
        $problemes = $problemeRepository->findActiveProblems();
        $problemesParEmplacement = [];
        foreach ($problemes as $probleme) {
            if (strpos($probleme->getEmplacement(), $cellule . ':' . $allee . '-') === 0) {
                $problemesParEmplacement[$probleme->getEmplacement()] = true;
            }
        }
        
        // Préparer les données pour l'affichage
        $emplacementsData = [
            'gauche' => [],  // Côté impair
            'droite' => []   // Côté pair
        ];
        
        // Définir les limites des positions selon la logique
        $startImpair = 1;
        $startPair = 2;
        $endImpair = 151;
        $endPair = 152;
        
        // Logique spéciale pour C2
        if ($cellule === 'C2') {
            if ($allee === 'B') {
                $startImpair = 25;
            } elseif (in_array($allee, ['C', 'D', 'E', 'F', 'G', 'H'])) {
                $startImpair = 25;
                $startPair = 26;
            }
        }
        
        // Initialiser tous les emplacements avec un état vide
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
                    'nopal' => [],
                    'codeprod' => [],
                    'designation' => [],
                    'ucdispo' => 0,
                    'urdispo' => 0,
                    'uvtotal' => 0,
                    'uvensortie' => 0,
                    'probleme' => isset($problemesParEmplacement[$code])
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
                    'nopal' => [],
                    'codeprod' => [],
                    'designation' => [],
                    'ucdispo' => 0,
                    'urdispo' => 0,
                    'uvtotal' => 0,
                    'uvensortie' => 0,
                    'probleme' => isset($problemesParEmplacement[$code])
                ];
            }
        }
        
        // Mettre à jour les emplacements avec les données réelles de la base de données
        foreach ($emplacements as $emplacement) {
            preg_match('/([A-Z]\d):([A-Z])-(\d+)-(\d+)/', $emplacement->getEmplacement(), $matches);
            
            if (isset($matches[3]) && isset($matches[4])) {
                $position = (int)$matches[3];
                $niveau = (int)$matches[4];
                
                $cote = ($position % 2 === 0) ? 'droite' : 'gauche';
                
                $isPlein = ($emplacement->getUrdispo() > 0 || $emplacement->getUcdispo() > 0);
                $isRouge = ($emplacement->getUrbloquee() > 0);
                $isEnSortie = ($emplacement->getUvensortie() > 0);
                
                if (isset($emplacementsData[$cote][$position][$niveau])) {
                    $currentData = $emplacementsData[$cote][$position][$niveau];
                    $count = $currentData['count'] + 1;
                    
                    if ($currentData['count'] === 0) {
                        $uvtotal = $emplacement->getUvtotal();
                        $ucdispo = $emplacement->getUcdispo();
                        $urdispo = $emplacement->getUrdispo();
                        $uvensortie = $emplacement->getUvensortie();
                        $nopals = [$emplacement->getNopal()];
                        $designations = [$emplacement->getDsignprod()];
                        $codeprods = [$emplacement->getCodeprod()];
                    } else {
                        $uvtotal = $currentData['uvtotal'] + $emplacement->getUvtotal();
                        $ucdispo = $currentData['ucdispo'] + $emplacement->getUcdispo();
                        $urdispo = $currentData['urdispo'] + $emplacement->getUrdispo();
                        $uvensortie = $currentData['uvensortie'] + $emplacement->getUvensortie();
                        
                        $nopals = array_merge($currentData['nopal'], [$emplacement->getNopal()]);
                        $designations = array_merge($currentData['designation'], [$emplacement->getDsignprod()]);
                        $codeprods = array_merge($currentData['codeprod'], [$emplacement->getCodeprod()]);
                    }
                    
                    $emplacementsData[$cote][$position][$niveau] = [
                        'id' => $emplacement->getId(),
                        'code' => $emplacement->getEmplacement(),
                        'plein' => $isPlein,
                        'rouge' => $isRouge || $currentData['rouge'],
                        'enSortie' => $isEnSortie || $currentData['enSortie'],
                        'multiple' => $count > 1,
                        'count' => $count,
                        'nopal' => $nopals,
                        'codeprod' => $codeprods,
                        'designation' => $designations,
                        'ucdispo' => $ucdispo,
                        'urdispo' => $urdispo,
                        'uvtotal' => $uvtotal,
                        'uvensortie' => $uvensortie,
                        'probleme' => $currentData['probleme'] // Conserver l'information du problème
                    ];
                }
            }
        }
        
        foreach (['gauche', 'droite'] as $cote) {
            ksort($emplacementsData[$cote]);
        }
        
        return $this->render('emplacement/index.html.twig', [
            'cellules' => $cellules,
            'celluleSelectionnee' => $cellule,
            'alleeSelectionnee' => $allee,
            'emplacements' => $emplacementsData
        ]);
    }

    #[Route('/probleme/signaler', name: 'app_probleme_signaler', methods: ['POST'])]
    public function signalerProbleme(
        Request $request, 
        EntityManagerInterface $em,
        InventairecompletRepository $inventaireRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        // Récupérer les informations de l'emplacement
        $emplacement = $data['emplacement'];
        $description = $data['description'];
        
        // Rechercher les palettes à cet emplacement
        $inventaires = $inventaireRepo->findBy(['emplacement' => $emplacement]);
        
        if (empty($inventaires)) {
            // Si aucune palette n'est trouvée, créer un problème générique pour l'emplacement
            $probleme = new Probleme();
            $probleme->setEmplacement($emplacement);
            $probleme->setNopal('N/A');
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
            // Créer un problème pour chaque palette trouvée
            foreach ($inventaires as $inventaire) {
                $probleme = new Probleme();
                $probleme->setEmplacement($emplacement);
                $probleme->setNopal($inventaire->getNopal());
                $probleme->setCodeprod($inventaire->getCodeprod());
                $probleme->setDsignprod($inventaire->getDsignprod());
                $probleme->setDescription($description);
                $probleme->setStatut('nouveau');
                $probleme->setDateSignalement(new \DateTime());
                $probleme->setUvtotal($inventaire->getUvtotal());
                $probleme->setUcdispo($inventaire->getUcdispo());
                $probleme->setUrdispo($inventaire->getUrdispo());
                $probleme->setUvensortie($inventaire->getUvensortie());
                $probleme->setUrbloquee($inventaire->getUrbloquee());
                $probleme->setZone($inventaire->getZone());
                
                // Stocker toutes les informations de la palette dans un champ JSON
                $infosPalette = [
                    'nopalinfo' => $inventaire->getNopalinfo(),
                    'emplacementdoublon' => $inventaire->getEmplacementdoublon(),
                    'dateentree' => $inventaire->getDateentree()->format('Y-m-d'),
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
        $stats = $repository->countByStatut();
        
        return $this->render('emplacement/problemes.html.twig', [
            'problemes' => $problemes,
            'stats' => $stats
        ]);
    }

    #[Route('/probleme/{id}/commenter', name: 'app_probleme_commenter', methods: ['POST'])]
    public function commenterProbleme(
        Probleme $probleme,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $commentaire = $data['commentaire'];
        
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
        $data = json_decode($request->getContent(), true);
        $commentaire = $data['commentaire'] ?? null;
        
        $probleme->setStatut('resolu');
        $probleme->setDateResolution(new \DateTime());
        
        if ($commentaire) {
            $probleme->setCommentaire($commentaire);
        }
        
        $em->flush();
        
        return new JsonResponse(['success' => true]);
    }
}