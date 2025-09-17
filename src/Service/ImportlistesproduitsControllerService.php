<?php

namespace App\Service;

use App\Entity\ListeProduits;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;

class ImportlistesproduitsControllerService
{
    private EntityManagerInterface $entityManager;
    private ?LoggerInterface $logger;
    
    private const BATCH_SIZE = 100; // Réduit de 200 à 100
    private const DEFAULT_SEUIL_REAPP = '0';

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger = null)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Import optimisé pour éviter les problèmes de mémoire
     */
    public function importFile(UploadedFile $file): int
    {
        // Désactiver le query logging en mode debug pour économiser la mémoire
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
        
        // Augmenter la limite mémoire temporairement
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');
        
        try {
            $this->entityManager->beginTransaction();
            
            // 1) Vider la table
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement('DELETE FROM liste_produits');
            
            // 2) Import avec lecture ligne par ligne (plus économe en mémoire)
            $count = $this->processFileByChunks($file);
            
            $this->entityManager->commit();
            
            $this->log('info', "Import terminé avec succès", ['rows_imported' => $count]);
            
            return $count;
            
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            $this->log('error', "Erreur lors de l'import", ['error' => $e->getMessage()]);
            throw new \RuntimeException("Erreur lors de l'import : " . $e->getMessage(), 0, $e);
        } finally {
            // Restaurer la limite mémoire originale
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Traitement par chunks pour économiser la mémoire
     */
    private function processFileByChunks(UploadedFile $file): int
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        
        $highestRow = $worksheet->getHighestRow();
        $count = 0;
        
        // On traite par petits blocs
        $chunkSize = 500; // Nombre de lignes à traiter d'un coup
        
        for ($startRow = 2; $startRow <= $highestRow; $startRow += $chunkSize) {
            $endRow = min($startRow + $chunkSize - 1, $highestRow);
            
            // Lire seulement le chunk actuel
            $chunkData = $worksheet->rangeToArray(
                "A{$startRow}:E{$endRow}",
                null,
                true,
                false
            );
            
            $count += $this->processChunk($chunkData);
            
            // Force la libération mémoire du chunk
            unset($chunkData);
            
            // Log du progrès
            $this->log('info', "Chunk traité", [
                'rows_processed' => $endRow,
                'total_rows' => $highestRow,
                'memory_usage' => memory_get_usage(true)
            ]);
        }
        
        // Libérer la mémoire du spreadsheet
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        
        return $count;
    }

    /**
     * Traite un chunk de données
     */
    private function processChunk(array $rows): int
    {
        $count = 0;
        
        foreach ($rows as $row) {
            // Ignorer lignes vides
            if (empty(array_filter($row, static fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $produit = $this->createProduitFromRow($row);
            
            if ($produit !== null) {
                $this->entityManager->persist($produit);
                $count++;
                
                // Flush plus fréquent avec un batch plus petit
                if ($count % self::BATCH_SIZE === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear(ListeProduits::class);
                    
                    // Force garbage collection
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }
            }
        }
        
        // Flush final du chunk
        $this->entityManager->flush();
        $this->entityManager->clear(ListeProduits::class);
        
        return $count;
    }

    /**
     * Crée un produit à partir d'une ligne de données
     */
    private function createProduitFromRow(array $row): ?ListeProduits
    {
        try {
            $ref = isset($row[0]) ? trim((string)$row[0]) : null;
            $des = isset($row[1]) ? trim((string)$row[1]) : null;
            $pinkg = isset($row[2]) ? trim((string)$row[2]) : null;
            $uvEnStock = isset($row[3]) ? trim((string)$row[3]) : null;
            $seuilreapp = isset($row[4]) ? trim((string)$row[4]) : null;

            if ($seuilreapp === null || $seuilreapp === '') {
                $seuilreapp = self::DEFAULT_SEUIL_REAPP;
            }

            // Validation basique (optionnelle)
            if (empty($ref)) {
                $this->log('warning', "Ligne ignorée : Ref vide", ['row' => $row]);
                return null;
            }

            $produit = new ListeProduits();
            $produit->setRef($ref);
            $produit->setDes($des);
            $produit->setPinkg($pinkg);
            $produit->setUvEnStock($uvEnStock);
            $produit->setSeuilreapp($seuilreapp);

            return $produit;
            
        } catch (\Throwable $e) {
            $this->log('error', "Erreur lors de la création du produit", [
                'row' => $row,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Version avec TRUNCATE (plus rapide mais nécessite des privilèges)
     */
    public function importFileWithTruncate(UploadedFile $file): int
    {
        // Désactiver le query logging
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
        
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');
        
        try {
            $connection = $this->entityManager->getConnection();
            
            // Optionnel : désactiver FK checks pour MySQL
            try {
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
                $connection->executeStatement('TRUNCATE TABLE liste_produits');
                $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Exception $e) {
                // Fallback si TRUNCATE échoue
                $connection->executeStatement('DELETE FROM liste_produits');
            }

            return $this->processFileByChunks($file);
            
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Validation de la structure du fichier
     */
    public function validateFileStructure(UploadedFile $file): array
    {
        $errors = [];
        
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Lire seulement la première ligne
            $headers = $worksheet->rangeToArray('A1:E1', null, true, false)[0] ?? [];
            
            $expectedHeaders = ['Ref', 'Des', 'Pinkg', 'UvEnStock', 'Seuilreapp'];
            
            for ($i = 0; $i < count($expectedHeaders); $i++) {
                if (!isset($headers[$i])) {
                    $errors[] = "Colonne " . chr(65 + $i) . " manquante";
                } elseif (empty(trim($headers[$i]))) {
                    $errors[] = "En-tête vide en colonne " . chr(65 + $i);
                }
            }
            
            // Libérer la mémoire
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
            
        } catch (\Throwable $e) {
            $errors[] = "Impossible de lire le fichier : " . $e->getMessage();
        }
        
        return $errors;
    }

    /**
     * Helper pour logging
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}