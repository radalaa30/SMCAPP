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

    private function validateNbRegr($value): ?int
    {
        if (empty($value)) {
            return null;
        }

        if (!is_numeric($value)) {
            return 1;
        }

        $number = (int)$value;

        if ($number > 999999) {
            return 1;
        }

        return $number;
    }

    private function convertToDate($value): DateTimeImmutable
    {
        try {
            if (!empty($value)) {
                if (is_numeric($value)) {
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

    #[Route('admin/import/suivi-preparation', name: 'app_import_suivi_preparation')]
    public function import(Request $request): Response
    {
        $message = '';
        $error = false;
        $importCount = 0;

        if ($request->isMethod('POST') && ($file = $request->files->get('file'))) {
            try {
                /// Récupérer la date du début et de la fin de la journée
                $startOfDay = new \DateTimeImmutable('today'); // Début de la journée (00:00:00)
                $endOfDay = new \DateTimeImmutable('tomorrow'); // Début du jour suivant (00:00:00)

                // Supprimer les enregistrements d'aujourd'hui
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

                foreach ($rows as $row) {
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

                        // Mise à jour de la date de modification
                        $suivi->updateTimestamp();

                        $this->entityManager->persist($suivi);
                        $importCount++;
                    }
                }

                $this->entityManager->flush();
                $message = "Import réussi ! $importCount lignes ont été importées.";
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
}
