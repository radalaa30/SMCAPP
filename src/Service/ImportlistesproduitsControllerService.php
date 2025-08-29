<?php

namespace App\Service;

use App\Entity\ListeProduits;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportlistesproduitsControllerService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function importFile(UploadedFile $file): int
    {
        // Supprimer toutes les données existantes
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM liste_produits');
        
        // Réinitialiser les séquences d'ID auto-incrémentés si nécessaire
        // Décommentez la ligne ci-dessous si vous utilisez MySQL et souhaitez réinitialiser l'auto-increment
        // $connection->executeStatement('ALTER TABLE liste_produits AUTO_INCREMENT = 1');
        
        // Charger le fichier Excel
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Récupérer les données (en supposant que la première ligne contient les en-têtes)
        $rows = $worksheet->toArray();
        $headers = array_shift($rows);
        
        $count = 0;
        
        foreach ($rows as $row) {
            // Ignorer les lignes vides
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Créer un nouvel objet ListeProduits pour chaque ligne
            $produit = new ListeProduits();
            
            // Associer les données en fonction de l'ordre des colonnes
            // Ajustez cet ordre selon la structure de votre fichier Excel
            $produit->setRef($row[0] ?? null);
            $produit->setDes($row[1] ?? null);
            $produit->setUvEnStock($row[2] ?? null);
            $produit->setNbrucPal($row[3] ?? ''); // Ce champ n'est pas nullable
            $produit->setPinkg($row[4] ?? null);
            
            // Persister l'entité
            $this->entityManager->persist($produit);
            $count++;
            
            // Flush toutes les 100 entités pour optimiser la mémoire
            if ($count % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                
                // Réinitialiser le gestionnaire d'entités pour libérer la mémoire
                // mais garder les objets ListeProduits en mémoire
                $this->entityManager->clear(ListeProduits::class);
            }
        }
        
        // Flush final pour les entités restantes
        $this->entityManager->flush();
        
        return $count;
    }
    
    /**
     * Méthode alternative utilisant TRUNCATE si vous avez les privilèges nécessaires
     * Cette méthode est généralement plus rapide pour de grandes tables
     */
    public function importFileWithTruncate(UploadedFile $file): int
    {
        // Supprimer toutes les données existantes avec TRUNCATE
        $connection = $this->entityManager->getConnection();
        
        // Désactiver temporairement les contraintes de clé étrangère si nécessaire
        // $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('TRUNCATE TABLE liste_produits');
        // $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        
        // Procéder à l'importation comme dans la méthode principale
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        
        $rows = $worksheet->toArray();
        $headers = array_shift($rows);
        
        $count = 0;
        
        foreach ($rows as $row) {
            if (empty(array_filter($row))) {
                continue;
            }
            
            $produit = new ListeProduits();
            $produit->setRef($row[0] ?? null);
            $produit->setDes($row[1] ?? null);
            $produit->setUvEnStock($row[2] ?? null);
            $produit->setNbrucPal($row[3] ?? '');
            $produit->setPinkg($row[4] ?? null);
            
            $this->entityManager->persist($produit);
            $count++;
            
            if ($count % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(ListeProduits::class);
            }
        }
        
        $this->entityManager->flush();
        
        return $count;
    }
    
    /**
     * Vérifie la structure du fichier Excel avant l'importation
     * Retourne true si la structure est valide, false sinon
     */
    public function validateFileStructure(UploadedFile $file): bool
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            $headers = $worksheet->toArray(null, true, true, true)[1];
            
            // Vérifier que les colonnes attendues sont présentes
            // Ajustez selon les en-têtes attendus dans votre fichier
            $requiredColumns = ['A', 'B', 'C', 'D', 'E']; // Ref, Des, UvEnStock, NbrucPal, Pinkg
            
            foreach ($requiredColumns as $column) {
                if (!isset($headers[$column]) || empty($headers[$column])) {
                    return false;
                }
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}