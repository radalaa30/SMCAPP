<?php

namespace App\Controller;

use App\Service\ImportlistesproduitsControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_ADMIN')]
class ImportlistesproduitsController extends AbstractController
{
    #[Route('/importproduits', name: 'app_import_produits')]
    public function index(): Response
    {
        return $this->render('import/listeproduits.html.twig');
    }
    
    #[Route('/importproduits/process', name: 'app_import_produits_process', methods: ['POST'])]
    public function process(Request $request, ImportlistesproduitsControllerService $importService): Response
    {
        
        $file = $request->files->get('excel_file');
        
        if (!$file) {
            $this->addFlash('error', 'Aucun fichier n\'a été téléchargé');
            return $this->redirectToRoute('app_import_produits');
        }
        
        // Vérifier que c'est bien un fichier Excel
        $validMimeTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream'
        ];
        
        if (!in_array($file->getMimeType(), $validMimeTypes)) {
            $this->addFlash('error', 'Le fichier doit être au format Excel (.xls ou .xlsx)');
            return $this->redirectToRoute('app_import_produits');
        }
        
        try {
            $count = $importService->importFile($file);
            $this->addFlash('success', $count . ' produits ont été importés avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'importation: ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_import_produits');
    }
}