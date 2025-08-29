<?php
namespace App\Service;

use App\Entity\Inventairecomplet;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use \DateTimeImmutable;

class ExcelInventaireImportService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function import(UploadedFile $file): void
    {
     // Effacer toutes les données existantes dans la table Inventairecomplet
     $this->clearTable();

     // Vérification de l'extension du fichier pour s'assurer que c'est un fichier Excel valide
     $extension = $file->getClientOriginalExtension();
     //if (!in_array($extension, ['xls', 'xlsx'])) {
      //   throw new \Exception("Le fichier doit être au format Excel (.xls ou .xlsx).");
     //}

     // Charger le fichier Excel
     $fileType = IOFactory::identify($file->getRealPath()); // Cette ligne détecte automatiquement le type de fichier (.xls ou .xlsx)
     $reader = IOFactory::createReader($fileType); // Crée un lecteur adapté au type du fichier
     $spreadsheet = $reader->load($file->getRealPath()); // Charge le fichier

        // Sélectionner la première feuille
        $sheet = $spreadsheet->getActiveSheet();

        // Parcourir toutes les lignes de la feuille
        foreach ($sheet->getRowIterator() as $row) {
            // Sauter la première ligne si elle contient les en-têtes
            if ($row->getRowIndex() === 1) {
                continue;
            }

            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Itérer sur toutes les cellules

            // Récupérer les valeurs des cellules pour chaque ligne
            $data = [];
            foreach ($cellIterator as $cell) {
                $data[] = $cell->getFormattedValue();
            }

            // Créer une nouvelle entité Inventairecomplet
            $inventaire = new Inventairecomplet();
            $inventaire->setNopalinfo($data[0] ?? '');
            $inventaire->setCodeprod($data[1] ?? '');
            $inventaire->setDsignprod($data[2] ?? '');
            $inventaire->setEmplacement($data[3] ?? '');
            $inventaire->setNopal($data[4] ?? '');
            $inventaire->setUrdispo((int)($data[5] ?? 0));
            $inventaire->setUcdispo((int)($data[6] ?? 0));
            $inventaire->setUvtotal((int)($data[7] ?? 0));
            $inventaire->setUvensortie((int)($data[8] ?? 0));
            $inventaire->setUrbloquee((int)($data[9] ?? 0));
            $inventaire->setZone($data[10] ?? '');
            $inventaire->setEmplacementdoublon($data[11] ?? '');

            // Gestion de la date d'entrée
            $dateString = $data[12] ?? '';  // Date venant de la cellule correspondante
            if ($dateString) {
                // Essayer de créer un objet DateTimeImmutable à partir du format 'd/m/Y'
                $date = \DateTime::createFromFormat('d/m/Y', $dateString);
                if ($date) {
                    // Si la date est valide, l'utiliser pour créer un objet DateTimeImmutable
                    $inventaire->setDateentree(DateTimeImmutable::createFromFormat('Y-m-d', $date->format('Y-m-d')));
                } else {
                    // Si la conversion échoue, lever une exception avec un message
                    throw new \Exception("La date '$dateString' n'a pas pu être convertie.");
                }
            } else {
                // Si pas de date, utiliser la date actuelle
                $inventaire->setDateentree(new DateTimeImmutable());
            }

            // Enregistrer l'entité en base de données
            $this->entityManager->persist($inventaire);
        }

        // Sauvegarder tous les enregistrements
        $this->entityManager->flush();
    }


     // Méthode pour effacer les données de la table
     private function clearTable(): void
     {
         // Créer une requête DQL pour supprimer toutes les entrées
         $query = $this->entityManager->createQuery('DELETE FROM ' . Inventairecomplet::class);
         $query->execute();
     }
}
