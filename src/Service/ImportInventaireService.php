<?php

namespace App\Service;

use App\Entity\Inventairecomplet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use DateTimeImmutable;
use Exception;

class ImportInventaireService
{
    private EntityManagerInterface $entityManager;
    private array $errors = [];

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Importe les données depuis un fichier Excel
     * @param string $filePath Chemin du fichier
     * @param string $originalFilename Nom original du fichier
     * @return array [nombre de lignes importées, erreurs]
     * @throws Exception Si le format du fichier n'est pas supporté
     */
    public function importFile(string $filePath, string $originalFilename): array
    {
        if (!$this->isValidExcelFile($originalFilename)) {
            throw new Exception("Format de fichier non supporté. Utilisez .xlsx ou .xls");
        }

        try {
            // Déterminer le type de fichier et créer le reader approprié
            $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            if ($extension === 'xls') {
                $reader = new Xls();
            } else {
                $reader = new Xlsx();
            }
            
            // Configurer le reader pour plus de tolérance avec les fichiers anciens
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            // Charger le fichier Excel
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, true);
            
            // Vérifier si le fichier n'est pas vide
            if (count($rows) <= 6) {
                throw new Exception("Le fichier est vide ou ne contient pas assez de lignes");
            }
            
            // Supprimer les 5 premières lignes (commencer à partir de la 6ème)
            for ($i = 1; $i <= 5; $i++) {
                if (isset($rows[$i])) {
                    unset($rows[$i]);
                }
            }
            
            $importedRows = 0;
            
            // Commencer une transaction
            $this->entityManager->beginTransaction();
            
            try {
                // Vider la table avant d'importer les nouvelles données
                $this->truncateInventaireTable();
                
                foreach ($rows as $rowIndex => $row) {
                    // Le $rowIndex représente maintenant le numéro de ligne réel dans le fichier
                    
                    // Vérifier si la ligne n'est pas vide
                    if ($this->isEmptyRow($row)) {
                        continue;
                    }
                    
                    try {
                        $inventaire = $this->createInventaireFromRow($row, $rowIndex);
                        $this->entityManager->persist($inventaire);
                        $importedRows++;
                        
                        // Flush tous les 20 enregistrements pour optimiser la mémoire
                        if ($importedRows % 20 === 0) {
                            $this->entityManager->flush();
                            $this->entityManager->clear(Inventairecomplet::class);
                        }
                    } catch (Exception $e) {
                        $this->errors[] = "Ligne $rowIndex : " . $e->getMessage();
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
     * Vide la table inventairecomplet
     */
    private function truncateInventaireTable(): void
    {
        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        
        // Option 1: Utiliser DQL pour supprimer tous les enregistrements
        $query = $this->entityManager->createQuery('DELETE FROM App\Entity\Inventairecomplet');
        $query->execute();
        
        // Option alternative si vous préférez la méthode native SQL (plus performante)
        // Décommenter les lignes ci-dessous et commenter les lignes au-dessus
        // $tableName = $this->entityManager->getClassMetadata(Inventairecomplet::class)->getTableName();
        // $connection->executeUpdate($platform->getTruncateTableSQL($tableName, true));
    }

    /**
     * Crée une entité Inventairecomplet à partir d'une ligne Excel
     * @param array $row Ligne du fichier Excel
     * @param int $rowNumber Numéro de la ligne pour les messages d'erreur
     * @return Inventairecomplet
     * @throws Exception Si les données sont invalides
     */
    private function createInventaireFromRow(array $row, int $rowNumber): Inventairecomplet
    {
        $inventaire = new Inventairecomplet();

        // Validation des champs obligatoires
        if (empty($row['A'])) {
            throw new Exception("Le champ Nopalinfo est obligatoire");
        }
        if (empty($row['B'])) {
            throw new Exception("Le champ Codeprod est obligatoire");
        }

        try {
            // Mapping exact avec les propriétés de l'entité
            $inventaire->setNopalinfo($this->sanitizeString($row['A'] ?? '', 50) ?? '');
            $inventaire->setCodeprod($this->sanitizeString($row['B'] ?? '', 50) ?? '');
            $inventaire->setDsignprod($this->sanitizeString($row['C'] ?? '', 255) ?? '');
            $inventaire->setEmplacement($this->sanitizeString($row['D'] ?? '', 50) ?? ''); // Utiliser une chaîne vide au lieu de null
            $inventaire->setNopal($this->sanitizeString($row['E'] ?? '', 50) ?? '');
            $inventaire->setUrdispo($this->parseInteger($row['F'] ?? 0));
            $inventaire->setUcdispo($this->parseInteger($row['G'] ?? 0));
            $inventaire->setUvtotal($this->sanitizeString($row['H'] ?? '0', 50) ?? '0');
            $inventaire->setUvensortie($this->parseInteger($row['I'] ?? 0));
            $inventaire->setUrbloquee($this->parseInteger($row['J'] ?? 0));
            $inventaire->setZone($this->sanitizeString($row['K'] ?? '', 50) ?? '');
            $inventaire->setEmplacementdoublon($this->sanitizeString($row['L'] ?? '', 50) ?? '');
            
            // Utiliser une date par défaut en cas d'échec de la conversion
            try {
                $inventaire->setDateentree($this->parseDate($row['M'] ?? null));
            } catch (Exception $e) {
                // Utiliser la date actuelle en cas d'erreur
                $inventaire->setDateentree(new DateTimeImmutable());
            }

        } catch (Exception $e) {
            throw new Exception("Erreur de conversion des données : " . $e->getMessage());
        }

        return $inventaire;
    }

    /**
     * Nettoie et valide une chaîne de caractères
     * @param mixed $value Valeur à nettoyer
     * @param int $maxLength Longueur maximale
     * @return string|null Chaîne nettoyée ou null si vide
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
     * @param mixed $value Valeur à convertir
     * @return int|null Entier ou null si invalide
     */
    private function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (!is_numeric($value)) {
            return 0;
        }
        return (int)$value;
    }

    /**
     * Convertit une chaîne en date
     * @param mixed $value Valeur à convertir
     * @return DateTimeImmutable Date convertie
     * @throws Exception Si la conversion échoue
     */
    private function parseDate($value): DateTimeImmutable
    {
        // Si aucune valeur n'est fournie, retourner la date du jour
        if ($value === null || $value === '') {
            return new DateTimeImmutable();
        }
        
        // Si c'est déjà un objet DateTimeImmutable
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        
        // Si c'est un objet DateTime
        if ($value instanceof \DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }
        
        // Si c'est une valeur numérique (format Excel)
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeImmutable((float)$value);
            } catch (Exception $e) {
                // En cas d'échec, on continue avec les autres méthodes
            }
        }
        
        // Si c'est une chaîne
        if (is_string($value)) {
            // Essayer différents formats de date courants
            $formats = [
                'd/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y', 'd/m/Y H:i:s', 'Y-m-d H:i:s',
                'd/m/y', 'd-m-y', 'y-m-d', 'm/d/y'
            ];
            
            foreach ($formats as $format) {
                $date = DateTimeImmutable::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            }
            
            // Si aucun format standard ne fonctionne, essayer avec strtotime
            try {
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    return new DateTimeImmutable('@' . $timestamp);
                }
            } catch (Exception $e) {
                // Continuer si cela échoue
            }
        }
        
        // Si toutes les tentatives échouent, retourner la date du jour
        return new DateTimeImmutable();
    }

    /**
     * Vérifie si le fichier est un fichier Excel valide
     * @param string $filename Nom du fichier
     * @return bool True si le fichier est valide
     */
    private function isValidExcelFile(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'xls'];
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Vérifie si une ligne est vide
     * @param array $row Ligne à vérifier
     * @return bool True si la ligne est vide
     */
    private function isEmptyRow(array $row): bool
    {
        // Vérifier les colonnes principales
        return empty($row['A']) && empty($row['B']) && empty($row['C']);
    }
}