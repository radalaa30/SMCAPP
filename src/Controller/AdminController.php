<?php
namespace App\Controller;

use App\Repository\DemandeReapproRepository; // Ajoutez cet import
use App\Entity\DemandeReappro;
use App\Form\DemandeReapproType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{ 
       
        #[Route('/demandes', name: 'admin_dashboard')]
        public function indexdemanes(Request $request, DemandeReapproRepository $repository): Response
        {
            // Initialisation des filtres
            $filters = [];
            
            // Récupération de la date de début
            if ($request->query->has('dateDebut')) {
                $dateDebut = $request->query->get('dateDebut');
                $filters['dateDebut'] = $dateDebut;
            } else {
                $dateDebut = (new \DateTime())->format('Y-m-d');
                $filters['dateDebut'] = $dateDebut;
            }
        
            // Récupération de la date de fin
            if ($request->query->has('dateFin')) {
                $dateFin = $request->query->get('dateFin');
                $filters['dateFin'] = $dateFin;
            } else {
                $dateFin = null;
            }
        
            // Récupération du cariste
            if ($request->query->has('idCariste')) {
                $idCariste = $request->query->get('idCariste');
                if ($idCariste) {
                    $filters['idCariste'] = $idCariste;
                }
            }
        
            // Récupération du préparateur
            if ($request->query->has('idPreparateur')) {
                $idPreparateur = $request->query->get('idPreparateur');
                if ($idPreparateur) {
                    $filters['idPreparateur'] = $idPreparateur;
                }
            }
        
            // Récupération du username du préparateur
            if ($request->query->has('UsernamePrep')) {
                $UsernamePrep = $request->query->get('UsernamePrep');
                if ($UsernamePrep) {
                    $filters['UsernamePrep'] = $UsernamePrep;
                }
            }
        
            // Récupération du username du cariste
            if ($request->query->has('UsernameCariste')) {
                $UsernameCariste = $request->query->get('UsernameCariste');
                if ($UsernameCariste) {
                    $filters['UsernameCariste'] = $UsernameCariste;
                }
            }
        
            // Récupération des demandes filtrées
            $demandes = $repository->findByFilters($filters);
        
            // Initialisation des compteurs généraux
            $nbPalettesEnAttente = 0;    // Statut 'A'
            $nbPalettesEnCours = 0;      // Statut 'Encours'
            $nbLignesTerminees = 0;      // Total des statuts 'V'
        
            // Tableau pour compter les palettes par cariste
            $palettesParCariste = [];
        
            // Calcul des statistiques
            foreach ($demandes as $demande) {
                switch ($demande->getStatut()) {
                    case 'A':
                        $nbPalettesEnAttente++;
                        break;
                    case 'Encours':
                        $nbPalettesEnCours++;
                        break;
                    case 'V':
                        $nbLignesTerminees++;
                        // Compte des palettes par cariste
                        if ($demande->getUsernameCariste()) {
                            $usernameCariste = $demande->getUsernameCariste();
                            if (!isset($palettesParCariste[$usernameCariste])) {
                                $palettesParCariste[$usernameCariste] = 0;
                            }
                            $palettesParCariste[$usernameCariste]++;
                        }
                        break;
                }
            }
        
            // Tri du tableau des palettes par cariste (du plus grand au plus petit)
            arsort($palettesParCariste);
        
            // Récupération des listes des caristes et préparateurs
            $caristes = $repository->findAllCaristesWithUsernames();
            $preparateurs = $repository->findAllPreparateursWithUsernames();
        
            // Calcul des totaux
            $totalDemandes = count($demandes);
            $totalEnCours = $nbPalettesEnAttente + $nbPalettesEnCours;
        
            return $this->render('admin/demandes_reappro.html.twig', [
                'demandes' => $demandes,
                'caristes' => $caristes,
                'preparateurs' => $preparateurs,
                'filters' => [
                    'dateDebut' => $dateDebut ?? '',
                    'dateFin' => $dateFin ?? '',
                    'idCariste' => $idCariste ?? '',
                    'idPreparateur' => $idPreparateur ?? '',
                    'UsernamePrep' => $UsernamePrep ?? '',
                    'UsernameCariste' => $UsernameCariste ?? '',
                ],
                'stats' => [
                    'palettesEnAttente' => $nbPalettesEnAttente,
                    'palettesEnCours' => $nbPalettesEnCours,
                    'lignesTermineesCariste' => $nbLignesTerminees,
                    'totalDemandes' => $totalDemandes,
                    'totalEnCours' => $totalEnCours,
                    'palettesParCariste' => $palettesParCariste,
                ],
            ]);
        }
        #[Route('/manages', name: 'admin_manage')]
        public function indexmanages(Request $request, DemandeReapproRepository $repository): Response
        {
        
        }
}