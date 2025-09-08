<?php

namespace App\Controller;

use App\Service\InventaireImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class ImportInventaireController extends AbstractController
{
    #[Route('/import/inventaire', name: 'app_import_inventaire', methods: ['GET', 'POST'])]
    public function import(Request $request, InventaireImportService $importService): Response
    {
        $form = $this->createFormBuilder()
            ->add('file', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Fichier Excel',
                'help' => 'Formats supportés : .xls (Excel 97-2003) et .xlsx'
            ])
            ->add('submit', \Symfony\Component\Form\Extension\Core\Type\SubmitType::class, [
                'label' => 'Importer'
            ])
            ->getForm();

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            
            if ($file) {
                try {
                    // Obtenir le chemin temporaire du fichier
                    $tempFilePath = $file->getPathname();
                    $originalFilename = $file->getClientOriginalName();
                    
                    // Utiliser le service pour l'importation
                    $result = $importService->importFile($tempFilePath, $originalFilename);
                    
                    // Afficher les messages de succès et d'erreur
                    $importCount = $result['imported'];
                    $this->addFlash('success', $importCount . ' lignes ont été importées avec succès.');
                    
                    // Afficher les erreurs éventuelles
                    if (!empty($result['errors'])) {
                        foreach ($result['errors'] as $error) {
                            $this->addFlash('warning', $error);
                        }
                    }
                    
                } catch (\Exception $e) {
                    // Log l'erreur complète
                    error_log('Erreur d\'import Excel: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
                    
                    $this->addFlash('error', 'Erreur lors de l\'import : ' . $e->getMessage());
                }
                
                return $this->redirectToRoute('app_import_inventaire');
            }
        }

        return $this->render('import/inventaire.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}