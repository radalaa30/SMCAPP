<?php
namespace App\Controller;

use App\Service\StockItService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class UpdateController extends AbstractController
{
    private $stockItService;
    
    public function __construct(StockItService $stockItService)
    {
        $this->stockItService = $stockItService;
    }
    
#[Route('/update/test-auth', name: 'test_auth')]


public function testAuth(): Response
{
    // Récupération des identifiants
    $username = $this->getParameter('stock_it_username');
    $password = $this->getParameter('stock_it_password');
    
    // Chemin où enregistrer le fichier Excel
    $storageDir = $this->getParameter('stock_it_files_directory');
    $today = date('Y-m-d');
    $excelPath = $storageDir . '/CDSUD_SUIVI_PREPARATION_DU_JOUR_' . $today . '.xls';
    
    // Début de la capture de la sortie
    ob_start();
    
    // Tentative d'authentification avec affichage des étapes
    $authResult = $this->stockItService->authenticate($username, $password);
    
    // Si l'authentification a réussi, tenter de cliquer sur Éditions
    $editionsResult = false;
    $reportResult = false;
    $imprimerResult = false;
    
    if ($authResult) {
        echo "<hr>";
        echo "<h2>Authentification réussie! Tentative de navigation vers Editions...</h2>";
        
        // Ajouter une pause pour s'assurer que la session est bien établie
        sleep(1);
        
        // Cliquer sur Éditions
        $editionsResult = $this->stockItService->clickEditions();
        
        // Si la navigation vers Editions a réussi, tenter de cliquer sur le rapport
        if ($editionsResult) {
            echo "<hr>";
            echo "<h2>Navigation vers Editions réussie! Tentative de clic sur le rapport...</h2>";
            
            // Ajouter une pause
            sleep(1);
            
            // Cliquer sur le rapport
            $reportResult = $this->stockItService->clickReport();
            
            // Si la sélection du rapport a réussi, tenter de cliquer sur Imprimer
            if ($reportResult) {
                echo "<hr>";
                echo "<h2>Sélection du rapport réussie! Tentative de clic sur Imprimer...</h2>";
                
                // Ajouter une pause
                sleep(1);
                
                // Cliquer sur Imprimer et télécharger le fichier
                $imprimerResult = $this->stockItService->clickImprimer($excelPath);
            }
        }
    }
    
    // Récupération de la sortie
    $output = ob_get_clean();
    
    // Construire le résultat HTML
    $html = '<html><body>' . $output;
    $html .= '<p><strong>Résultat de l\'authentification: ' . ($authResult ? 'Succès' : 'Échec') . '</strong></p>';
    
    if ($authResult) {
        $html .= '<p><strong>Résultat du clic sur Editions: ' . ($editionsResult ? 'Succès' : 'Échec') . '</strong></p>';
    }
    
    if ($editionsResult) {
        $html .= '<p><strong>Résultat du clic sur le rapport: ' . ($reportResult ? 'Succès' : 'Échec') . '</strong></p>';
    }
    
    if ($reportResult) {
        $html .= '<p><strong>Résultat du clic sur Imprimer: ' . ($imprimerResult ? 'Succès' : 'Échec') . '</strong></p>';
        
        if ($imprimerResult) {
            $filePath = $this->stockItService->getDownloadedFilePath();
            $fileSize = filesize($filePath);
            $html .= '<p><strong>Fichier téléchargé:</strong> ' . $filePath . ' (' . round($fileSize / 1024, 2) . ' KB)</p>';
            $html .= '<p><a href="/update/file/' . basename($filePath) . '" class="btn btn-success">Télécharger le fichier</a></p>';
        }
    }
    
    $html .= '</body></html>';
    
    // Affichage du résultat
    return new Response($html);
}
    #[Route('/update', name: 'update_files')]
    public function update(): Response
    {
        try {
            // Récupérer l'historique des fichiers téléchargés
            $storageDir = $this->getParameter('stock_it_files_directory');
            $history = $this->stockItService->getDownloadHistory($storageDir);
            
            // Page d'accueil pour afficher le bouton de mise à jour
            return $this->render('update/index.html.twig', [
                'history' => $history
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'affichage de la page: ' . $e->getMessage());
            return $this->redirectToRoute('home');
        }
    }
    
    
    #[Route('/update/download', name: 'download_file')]
    public function download(): Response
    {
        try {
            // 1. S'authentifier sur Stock-IT
            $username = $this->getParameter('stock_it_username');
            $password = $this->getParameter('stock_it_password');
            
            $this->addFlash('info', 'Tentative de connexion avec: ' . $username);
            
            $authenticated = $this->stockItService->authenticate($username, $password);
            
            if (!$authenticated) {
                $logFile = sys_get_temp_dir() . '/stockit_after_login_panther.html';
                if (file_exists($logFile)) {
                    $this->addFlash('warning', 'Vérifiez le fichier de log: ' . $logFile);
                }
                
                $this->addFlash('error', 'Échec de l\'authentification sur Stock-IT');
                return $this->redirectToRoute('update_files');
            }
            
            // 2. Télécharger le fichier de suivi de préparation du jour
            $tempFile = sys_get_temp_dir() . '/CDSUD_SUIVI_PREPARATION_DU_JOUR.xls';
            
            $downloaded = $this->stockItService->downloadSuiviPreparationExcel($tempFile);
            
            if (!$downloaded) {
                $this->addFlash('error', 'Échec du téléchargement du fichier');
                return $this->redirectToRoute('update_files');
            }
            
            // 3. Stocker une copie du fichier dans un dossier local
            $storageDir = $this->getParameter('stock_it_files_directory');
            $todayDate = date('Y-m-d');
            $storedFilename = 'CDSUD_SUIVI_PREPARATION_DU_JOUR_' . $todayDate . '.xls';
            $storedFilePath = $storageDir . '/' . $storedFilename;
            
            // Créer le répertoire s'il n'existe pas
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0777, true);
            }
            
            // Copier le fichier téléchargé dans le dossier de stockage
            copy($tempFile, $storedFilePath);
            
            $this->addFlash('success', 'Fichier téléchargé et stocké avec succès dans : ' . $storedFilePath);
            
            // 4. Retourner le fichier à l'utilisateur
            return new BinaryFileResponse($tempFile, 200, [
                'Content-Type' => 'application/vnd.ms-excel'
            ], true, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $storedFilename);
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du téléchargement: ' . $e->getMessage());
            return $this->redirectToRoute('update_files');
        }
    }
    
    
    #[Route('/update/file/{filename}', name: 'download_stored_file')]
    public function downloadStoredFile(string $filename): Response
    {
        $storageDir = $this->getParameter('stock_it_files_directory');
        $filePath = $storageDir . '/' . $filename;
        
        if (!file_exists($filePath)) {
            $this->addFlash('error', 'Le fichier demandé n\'existe pas.');
            return $this->redirectToRoute('update_files');
        }
        
        return new BinaryFileResponse($filePath, 200, [
            'Content-Type' => 'application/vnd.ms-excel'
        ], true, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
    }
}