<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Suividupreparationdujour;
use DateTimeImmutable;
use Exception;

class ExcelImportService
{
    private $entityManager;
    private $errors = [];

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Importe les données depuis un fichier Excel
     * @param string $filePath Chemin du fichier
     * @return array [nombre de lignes importées, erreurs]
     */
    public function importFile(string $filePath): array
    {
        try {
            // Charger le fichier Excel
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        
        // Obtenir toutes les données
        $data = $worksheet->toArray();
        
       
        
        // Vérifier si le fichier n'est pas vide
        if (count($data) <= 1) {
            throw new Exception("Le fichier est vide ou ne contient que l'en-tête");
        }
            
            // Supprimer l'en-tête
            array_shift($data);
            array_shift($data);
            
            
            $importedRows = 0;
            
            // Commencer une transaction
            $this->entityManager->beginTransaction();
            
            try {
                foreach ($data as $index => $row) {
                    $rowNumber = $index + 2; // +2 car on a enlevé l'en-tête et l'index commence à 0
                    
                    // Vérifier si la ligne n'est pas vide
                    if ($this->isEmptyRow($row)) {
                        continue;
                    }
                    
                    try {
                        $suivi = $this->createSuiviFromRow($row, $rowNumber);
                        $this->entityManager->persist($suivi);
                        $importedRows++;
                        
                        // Flush tous les 100 enregistrements pour optimiser la mémoire
                        if ($importedRows % 100 === 0) {
                            $this->entityManager->flush();
                            $this->entityManager->clear();
                        }
                    } catch (Exception $e) {
                        $this->errors[] = "Ligne $rowNumber : " . $e->getMessage();
                    }
                }
                
                // Flush final
                $this->entityManager->flush();
                $this->entityManager->commit();
                
                return [
                    'imported' => $importedRows,
                    'errors' => $this->errors
                ];
                
            } catch (Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            throw $e;
        }
    }

    /**
     * Crée une entité Suivi à partir d'une ligne Excel
     */
    private function createSuiviFromRow(array $row, int $rowNumber): Suividupreparationdujour
    {
        $suivi = new Suividupreparationdujour();

        // Validation des champs obligatoires
        if (empty($row[0])) {
            throw new Exception("Le CodeProduit est obligatoire");
        }
        if (empty($row[1])) {
            throw new Exception("Le Gencode_uv est obligatoire");
        }

        try {
            // Mapping exact avec les propriétés de l'entité
            $suivi->setCodeProduit($this->sanitizeString($row[0], 20))
                  ->setGencodeUv($this->sanitizeString($row[1], 20))
                  ->setNoPalette($this->sanitizeString($row[2], 20))
                  ->setNoPal($this->sanitizeString($row[3], 20))
                  ->setFlasher($this->sanitizeString($row[4], 20))
                  ->setZone($this->sanitizeString($row[5], 10))
                  ->setAdresse($this->sanitizeString($row[6], 20))
                  ->setNbPal($this->parseInteger($row[7]))
                  ->setNbCol($this->parseInteger($row[8]))
                  ->setNbArt($this->parseInteger($row[9]))
                  ->setNbRegr($this->parseInteger($row[10]))
                  ->setNoBl($this->sanitizeString($row[11], 20))
                  ->setDateLiv($this->parseDate($row[12] ?? 'now'))
                  ->setNoCmd($this->sanitizeString($row[13], 20))
                  ->setClient($this->sanitizeString($row[14], 100))
                  ->setStatutCde($this->sanitizeString($row[15], 20))
                  ->setCodeClient($this->sanitizeString($row[16], 20))
                  ->setPreparateur($this->sanitizeString($row[17], 20))
                  ->setTransporteur($this->sanitizeString($row[18], 20));

        } catch (Exception $e) {
            throw new Exception("Erreur de conversion des données : " . $e->getMessage());
        }

        return $suivi;
    }

    /**
     * Nettoie et valide une chaîne de caractères
     */
    private function sanitizeString($value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim(strip_tags((string) $value));
        return mb_substr($value, 0, $maxLength);
    }

    /**
     * Convertit une valeur en entier
     */
    private function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int)$value;
    }

    /**
     * Convertit une chaîne en date
     */
    private function parseDate($value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        
        if (is_numeric($value)) {
            // Conversion depuis le format de date Excel
            return DateTimeImmutable::createFromFormat('U', (int)(($value - 25569) * 86400));
        }
        
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $e) {
            throw new Exception("Format de date invalide");
        }
    }

    /**
     * Vérifie si le fichier est un fichier Excel valide
     */


    private function isValidExcelFile(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'xls', 'xlsm', 'XLS'];
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Vérifie si une ligne est vide
     */
    private function isEmptyRow(array $row): bool
    {
        return empty(array_filter($row, fn($value) => $value !== null && $value !== ''));
    }
}