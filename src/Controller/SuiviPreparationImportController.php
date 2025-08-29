<?php
// src/Controller/SuiviPreparationImportController.php
namespace App\Controller;

use App\Entity\Suividupreparationdujour;
use App\Repository\SuividupreparationdujourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use PhpOffice\PhpSpreadsheet\IOFactory;
use DateTimeImmutable;

class SuiviPreparationImportController extends AbstractController
{
    private $repository;
    private $entityManager;

    public function __construct(
        SuividupreparationdujourRepository $repository,
        EntityManagerInterface $entityManager
    ) {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }

    private function getStringValue($value): ?string
    {
        return !empty($value) ? (string)$value : '';
    }

    /**
     * Validation du champ Nb_regr - retourne un string car c'est un varchar(100) en BDD
     */
    private function validateNbRegr($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            return '1';
        }

        $number = (int)$value;

        if ($number > 999999) {
            return '1';
        }

        return (string)$number;
    }

    private function convertToDate($value): DateTimeImmutable
    {
        try {
            if (!empty($value)) {
                if (is_numeric($value)) {
                    // Conversion des dates Excel (nombre de jours depuis 1900-01-01)
                    $unixTimestamp = ($value - 25569) * 86400;
                    return new DateTimeImmutable('@' . $unixTimestamp);
                }
                return new DateTimeImmutable($value);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, retourner la date actuelle
        }
        return new DateTimeImmutable();
    }

    private function convertToMutableDate($value): ?\DateTime
    {
        try {
            if (!empty($value)) {
                if (is_numeric($value)) {
                    // Conversion des dates Excel
                    $unixTimestamp = ($value - 25569) * 86400;
                    return new \DateTime('@' . $unixTimestamp);
                }
                return new \DateTime($value);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, retourner null
        }
        return null;
    }

    /**
     * Méthode spécifique pour convertir la colonne valider (peut être vide)
     */
    private function convertValidationDate($value): ?\DateTime
    {
        // Vérifier si la cellule existe dans le tableau
        if (!isset($value) || $value === null || $value === '' || $value === 0) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                // Pour les dates Excel, vérifier que ce n'est pas 0
                if ($value == 0) {
                    return null;
                }
                
                // Conversion des dates Excel (nombre de jours depuis 1900-01-01)
                $unixTimestamp = ($value - 25569) * 86400;
                return new \DateTime('@' . $unixTimestamp);
            }
            
            // Si c'est une chaîne de date
            $stringValue = (string)$value;
            
            // Gérer le format français DD/MM/YYYY
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $stringValue, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                return \DateTime::createFromFormat('d/m/Y', "$day/$month/$year");
            }
            
            // Gérer le format DD-MM-YYYY
            if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $stringValue, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                return \DateTime::createFromFormat('d-m-Y', "$day-$month-$year");
            }
            
            // Tentative de parsing standard en dernier recours
            return new \DateTime($stringValue);
            
        } catch (\Exception $e) {
            // En cas d'erreur de conversion, retourner null (pas validé)
            return null;
        }
    }

    #[Route('admin/import/suivi-preparation', name: 'app_import_suivi_preparation')]
    public function import(Request $request): Response
    {
        $message = '';
        $error = false;
        $importCount = 0;

        if ($request->isMethod('POST') && ($file = $request->files->get('file'))) {
            try {
                // Récupérer la date du début et de la fin de la journée
                $startOfDay = new \DateTimeImmutable('today'); // Début de la journée (00:00:00)
                $endOfDay = new \DateTimeImmutable('tomorrow'); // Début du jour suivant (00:00:00)

                // Supprimer les enregistrements d'aujourd'hui basés sur updatedAt
                $this->entityManager->createQuery('
                    DELETE FROM App\Entity\Suividupreparationdujour s
                    WHERE s.updatedAt BETWEEN :startOfDay AND :endOfDay
                ')
                    ->setParameter('startOfDay', $startOfDay)
                    ->setParameter('endOfDay', $endOfDay)
                    ->execute();

                $spreadsheet = IOFactory::load($file->getPathname());
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Supprime les trois premières lignes (en-têtes)
                array_splice($rows, 0, 3);

                foreach ($rows as $index => $row) {
                    $codeClient = $this->getStringValue($row[16]);

                    // Vérifie si CodeClient est non null et commence par CI999 ou C0
                    if ($codeClient !== '' && (str_starts_with($codeClient, 'CI999') || str_starts_with($codeClient, 'C0'))) {
                        $suivi = new Suividupreparationdujour();

                        // Mapping des données avec gestion des valeurs nulles
                        $suivi->setCodeProduit($this->getStringValue($row[0]))
                            ->setGencodeUv($this->getStringValue($row[1]))
                            ->setNoPalette($this->getStringValue($row[2]))
                            ->setNoPal($this->getStringValue($row[3]))
                            ->setFlasher($this->getStringValue($row[4]))
                            ->setZone($this->getStringValue($row[5]))
                            ->setAdresse($this->getStringValue($row[6]))
                            ->setNbPal(!empty($row[7]) ? (int)$row[7] : 0)
                            ->setNbCol(!empty($row[8]) ? (int)$row[8] : 0)
                            ->setNbArt(!empty($row[9]) ? (int)$row[9] : 0)
                            ->setNbRegr($this->validateNbRegr($row[10]))
                            ->setNoBl($this->getStringValue($row[11]))
                            ->setDateLiv($this->convertToDate($row[12]))
                            ->setNoCmd($this->getStringValue($row[13]))
                            ->setClient($this->getStringValue($row[14]))
                            ->setStatutCde($this->getStringValue($row[15]))
                            ->setCodeClient($codeClient)
                            ->setPreparateur($this->getStringValue($row[17]))
                            ->setTransporteur($this->getStringValue($row[18]));

                        // Gestion du champ valider depuis le fichier Excel (colonne 20)
                        if (isset($row[20])) {
                            $validationDate = $this->convertValidationDate($row[20]);
                            $suivi->setValider($validationDate);
                        } else {
                            $suivi->setValider(null);
                        }

                        // Mise à jour automatique avec la date actuelle
                        $suivi->updateTimestamp();

                        $this->entityManager->persist($suivi);
                        $importCount++;
                    }

                    // Flush par batch de 100 pour optimiser les performances
                    if (($index + 1) % 100 === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    }
                }

                // Flush final pour les derniers enregistrements
                $this->entityManager->flush();
                
                // Vérifier ce qui a été sauvé en base
                $totalRecords = $this->entityManager->createQuery('
                    SELECT COUNT(s.id)
                    FROM App\Entity\Suividupreparationdujour s
                    WHERE s.updatedAt BETWEEN :startOfDay AND :endOfDay
                ')
                    ->setParameter('startOfDay', $startOfDay)
                    ->setParameter('endOfDay', $endOfDay)
                    ->getSingleScalarResult();
                
                $validatedRecords = $this->entityManager->createQuery('
                    SELECT COUNT(s.id)
                    FROM App\Entity\Suividupreparationdujour s
                    WHERE s.valider IS NOT NULL 
                    AND s.updatedAt BETWEEN :startOfDay AND :endOfDay
                ')
                    ->setParameter('startOfDay', $startOfDay)
                    ->setParameter('endOfDay', $endOfDay)
                    ->getSingleScalarResult();
                
                $message = "Import réussi ! $importCount lignes importées. Total en base : $totalRecords enregistrements, dont $validatedRecords validés.";
                
            } catch (\Exception $e) {
                $message = 'Erreur lors de l\'import : ' . $e->getMessage();
                $error = true;
            }
        }

        return $this->render('suivi/index.html.twig', [
            'message' => $message,
            'error' => $error
        ]);
    }

    /**
     * Méthode pour récupérer les statistiques d'import
     */
    #[Route('admin/suivi-preparation/stats', name: 'app_suivi_preparation_stats')]
    public function getStats(): Response
    {
        try {
            $startOfDay = new \DateTimeImmutable('today');
            $endOfDay = new \DateTimeImmutable('tomorrow');
            
            // Compter les enregistrements créés aujourd'hui (basé sur updatedAt)
            $totalToday = $this->entityManager->createQuery('
                SELECT COUNT(s.id) FROM App\Entity\Suividupreparationdujour s
                WHERE s.updatedAt BETWEEN :startOfDay AND :endOfDay
            ')
                ->setParameter('startOfDay', $startOfDay)
                ->setParameter('endOfDay', $endOfDay)
                ->getSingleScalarResult();

            // Compter les enregistrements validés aujourd'hui
            $validated = $this->entityManager->createQuery('
                SELECT COUNT(s.id) FROM App\Entity\Suividupreparationdujour s
                WHERE s.valider IS NOT NULL
                AND s.updatedAt BETWEEN :startOfDay AND :endOfDay
            ')
                ->setParameter('startOfDay', $startOfDay)
                ->setParameter('endOfDay', $endOfDay)
                ->getSingleScalarResult();
            
            return $this->json([
                'total_today' => (int)$totalToday,
                'validated' => (int)$validated,
                'pending' => (int)$totalToday - (int)$validated
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Méthode pour valider un suivi
     */
    #[Route('admin/suivi-preparation/validate/{id}', name: 'app_suivi_preparation_validate', methods: ['POST'])]
    public function validateSuivi(int $id): Response
    {
        try {
            $suivi = $this->repository->find($id);
            
            if (!$suivi) {
                return $this->json(['error' => 'Suivi non trouvé'], 404);
            }

            $suivi->setValider(new \DateTime());
            $this->entityManager->flush();

            return $this->json(['success' => 'Suivi validé avec succès']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}