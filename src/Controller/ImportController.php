<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ExcelImportService;
use App\Entity\ImportHistory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\DBALException;
use Doctrine\Persistence\ManagerRegistry;

use Exception;

#[IsGranted('ROLE_ADMIN')]
class ImportController extends AbstractController  
{
  // src/Controller/ImportController.php
#[Route('/import', name: 'import_excel')]
public function import(
    Request $request, 
    ExcelImportService $importService,
    EntityManagerInterface $entityManager,
    ManagerRegistry $doctrine
): Response {
    try {
        if ($request->isMethod('POST')) {
            $file = $request->files->get('file');
            
            if (!$file) {
                throw new Exception("Aucun fichier n'a été fourni");
            }

            $originalExtension = strtolower($file->getClientOriginalExtension());
            //if (!in_array($originalExtension, ['xlsx', 'xls', 'xlsm'])) {
              //  throw new Exception("Le fichier doit être au format .xlsx, .xlsm ou .xls");
           // }

            try {
                $filePath = $file->getPathname();
                $result = $importService->importFile($filePath);
                
                // Réinitialiser l'EntityManager pour l'historique
                $entityManager = $doctrine->resetManager();
                
                $importHistory = new ImportHistory();
                $importHistory->setFileName($file->getClientOriginalName());
                $importHistory->setImportedAt(new \DateTime());
                $importHistory->setRecordCount($result['imported']);
                $importHistory->setStatus('success');
                
                if (!empty($result['errors'])) {
                    $importHistory->setStatus('warning');
                    $importHistory->setErrors(json_encode($result['errors']));
                    
                    foreach ($result['errors'] as $error) {
                        $this->addFlash('warning', $error);
                    }
                }

                $entityManager->persist($importHistory);
                $entityManager->flush();

                $this->addFlash('success', "{$result['imported']} lignes ont été importées avec succès!");
                
            } catch (\Exception $e) {
                $entityManager = $doctrine->resetManager();
                
                $importHistory = new ImportHistory();
                $importHistory->setFileName($file->getClientOriginalName());
                $importHistory->setImportedAt(new \DateTime());
                $importHistory->setStatus('error');
                $importHistory->setErrors($e->getMessage());
                
                $entityManager->persist($importHistory);
                $entityManager->flush();
                
                $this->addFlash('error', "Erreur lors de l'import : " . $e->getMessage());
            }
        }
    } catch (\Exception $e) {
        $this->addFlash('error', "Erreur globale : " . $e->getMessage());
    }

    return $this->render('import/index.html.twig');
}
}
