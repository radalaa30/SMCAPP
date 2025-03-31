<?php
// src/Service/InventaireService.php

namespace App\Service;

use App\Repository\SuividupreparationdujourRepository;
use App\Repository\InventairecompletRepository;
use Doctrine\ORM\EntityManagerInterface;

class InventaireService
{
    private $suiviRepository;
    private $inventaireRepository;
    private $entityManager;

    public function __construct(
        SuividupreparationdujourRepository $suiviRepository,
        InventairecompletRepository $inventaireRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->suiviRepository = $suiviRepository;
        $this->inventaireRepository = $inventaireRepository;
        $this->entityManager = $entityManager;
    }

    public function mettreAJourUvensortie()
    {
        // Récupérer tous les inventaires
        $inventaires = $this->inventaireRepository->findAll();
        $compteur = 0;

        foreach ($inventaires as $inventaire) {
            // Pour chaque inventaire, chercher les informations dans Suividupreparationdujour
            $codeProduit = $inventaire->getCodeprod();
            $adresse = $inventaire->getEmplacement();
            
            // Récupérer la somme des Nb_art pour cette référence et cette adresse
            $totalSorti = $this->suiviRepository->getTotalSortiParProduitEtAdresse(
                $codeProduit, 
                $adresse
            );
            
            // Mettre à jour Uvensortie seulement si nécessaire
            if ($inventaire->getUvensortie() != $totalSorti) {
                $inventaire->setUvensortie($totalSorti);
                $compteur++;
            }
        }
        
        // Sauvegarder les modifications
        $this->entityManager->flush();
        
        return $compteur;
    }

    public function getQuantiteDisponible($codeProduit, $adresse)
    {
        $inventaire = $this->inventaireRepository->findOneBy([
            'codeprod' => $codeProduit,
            'emplacement' => $adresse
        ]);
        
        if (!$inventaire) {
            return 0;
        }
        
        return $inventaire->getUvtotal() - $inventaire->getUvensortie();
    }
}