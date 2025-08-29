<?php

namespace App\Controller;

use App\Service\SeleniumStockItService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class StockItController extends AbstractController
{
    #[Route('/stock-it', name: 'stock_it_index')]

    public function index(): Response
    {
        return $this->render('stock_it/index.html.twig');
    }

    #[Route('/stock-it/download', name: 'stock_it_download')]

    public function download(SeleniumStockItService $stockItService): Response
    {
        // Récupérer les identifiants depuis les paramètres (configurés dans services.yaml)
        $username = $this->getParameter('stockit.username');
        $password = $this->getParameter('stockit.password');
        
        // Définir le chemin où le fichier sera sauvegardé
        $filename = 'rapport_stockit_' . date('Ymd_His') . '.xls';
        $localPath = $this->getParameter('kernel.project_dir') . '/var/downloads/' . $filename;
        
        try {
            // Étape 1: Authentification au site
            if (!$stockItService->authenticate($username, $password)) {
                return $this->json([
                    'success' => false, 
                    'error' => 'Échec de l\'authentification. Vérifiez vos identifiants.'
                ]);
            }
            
            // Étape 2: Navigation vers le menu Editions
            if (!$stockItService->clickEditions()) {
                $stockItService->closeDriver();
                return $this->json([
                    'success' => false, 
                    'error' => 'Échec de la navigation vers le menu Editions.'
                ]);
            }
            
            // Étape 3: Sélection du rapport spécifique
            if (!$stockItService->clickReport()) {
                $stockItService->closeDriver();
                return $this->json([
                    'success' => false, 
                    'error' => 'Échec de la sélection du rapport CDSUD_SUIVI_PREPARATION_DU_JOUR.'
                ]);
            }
            
            // Étape 4: Clic sur le bouton Imprimer et téléchargement du fichier
            if (!$stockItService->clickImprimer($localPath)) {
                $stockItService->closeDriver();
                return $this->json([
                    'success' => false, 
                    'error' => 'Échec du téléchargement du rapport. Le bouton Imprimer n\'a pas fonctionné correctement.'
                ]);
            }
            
            // Tout s'est bien passé, fermer le driver
            $stockItService->closeDriver();
            
            // Vérifier que le fichier existe réellement
            if (!file_exists($localPath)) {
                return $this->json([
                    'success' => false, 
                    'error' => 'Le fichier a été téléchargé mais n\'a pas été trouvé à l\'emplacement attendu.'
                ]);
            }
            
            // Renvoyer le fichier à l'utilisateur
            return $this->file(
                $localPath,
                'rapport_stockit_' . date('Y-m-d') . '.xls',
                ResponseHeaderBag::DISPOSITION_ATTACHMENT
            );
        } catch (\Exception $e) {
            // Fermer le driver en cas d'erreur
            $stockItService->closeDriver();
            
            // Journaliser l'erreur
            $this->get('logger')->error('Erreur lors du téléchargement du rapport Stock-IT: ' . $e->getMessage());
            
            // Renvoyer une réponse d'erreur avec des détails
            return $this->json([
                'success' => false, 
                'error' => 'Erreur lors du téléchargement: ' . $e->getMessage(),
                'trace' => $this->getParameter('kernel.debug') ? $e->getTraceAsString() : null
            ]);
        }
    }

    
    #[Route('/stock-it/debug-credentials', name: 'stock_it_debug_credentials')]

    public function debugCredentials(Request $request): Response
    {
        try {
            // Récupérer les identifiants configurés
            $username = $this->getParameter('stockit.username') ?? 'Non défini';
            $password = $this->getParameter('stockit.password') ?? 'Non défini';
            
            // Récupérer les variables d'environnement
            $envUsername = getenv('STOCKIT_USERNAME') ?: 'Non défini';
            $envPassword = getenv('STOCKIT_PASSWORD') ?: 'Non défini';
            
            // Masquer partiellement le mot de passe pour la sécurité
            $maskedPassword = !empty($password) && $password !== 'Non défini' 
                ? substr($password, 0, 2) . '****' . substr($password, -2) 
                : $password;
                
            $maskedEnvPassword = !empty($envPassword) && $envPassword !== 'Non défini' 
                ? substr($envPassword, 0, 2) . '****' . substr($envPassword, -2) 
                : $envPassword;
            
            // Informations sur les paramètres du serveur
            $serverInfo = [
                'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'Non défini',
                'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? 'Non défini',
                'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'Non défini',
                'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'Non défini',
                'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? 'Non défini',
                'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? 'Non défini',
                'PHP_VERSION' => PHP_VERSION,
                'OS' => PHP_OS,
            ];
            
            // Informations sur la configuration Symfony
            $symfonyInfo = [
                'Environment' => $this->getParameter('kernel.environment'),
                'Debug Mode' => $this->getParameter('kernel.debug') ? 'Activé' : 'Désactivé',
                'Project Dir' => $this->getParameter('kernel.project_dir'),
                'Cache Dir' => $this->getParameter('kernel.cache_dir'),
                'Logs Dir' => $this->getParameter('kernel.logs_dir'),
            ];
            
            // Informations supplémentaires sur les fichiers de configuration
            $configFiles = [
                '.env' => file_exists($this->getParameter('kernel.project_dir') . '/.env'),
                '.env.local' => file_exists($this->getParameter('kernel.project_dir') . '/.env.local'),
                'services.yaml' => file_exists($this->getParameter('kernel.project_dir') . '/config/services.yaml'),
            ];
            
            return $this->json([
                'credentials' => [
                    'from_parameters' => [
                        'username' => $username,
                        'password' => $maskedPassword,
                    ],
                    'from_env' => [
                        'username' => $envUsername,
                        'password' => $maskedEnvPassword,
                    ],
                ],
                'server_info' => $serverInfo,
                'symfony_info' => $symfonyInfo,
                'config_files' => $configFiles,
                'timestamp' => new \DateTime(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération des informations: ' . $e->getMessage(),
            ]);
        }
    }

    #[Route('/stock-it/debug-session', name: 'stock_it_debug_session')]
    public function debugSession(SessionInterface $session): Response
    {
        // Récupérer toutes les données de session
        $sessionData = [];
        foreach ($session->all() as $key => $value) {
            $sessionData[$key] = is_object($value) ? get_class($value) : $value;
        }
        
        // Informations sur la session PHP
        $sessionInfo = [
            'session_id' => session_id(),
            'session_name' => session_name(),
            'session_status' => session_status(),
            'session_cookie_params' => session_get_cookie_params(),
        ];
        
        return $this->json([
            'session_data' => $sessionData,
            'session_info' => $sessionInfo,
            'cookies' => $_COOKIE,
            'timestamp' => new \DateTime(),
        ]);
    }

   #[Route('/stock-it/status', name: 'stock_it_status')]
    public function status(): Response
    {
        try {
            // Vérifier que ChromeDriver est accessible
            $context = stream_context_create(['http' => ['timeout' => 3]]);
            $status = @file_get_contents('http://localhost:9515/status', false, $context);
            
            $chromeDriverStatus = $status !== false;
            
            // Vérifier si Chrome est installé
            $chromeInstalled = false;
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                $chromeInstalled = file_exists('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe') || 
                                   file_exists('C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe');
            } else {
                // Linux/Mac
                exec('which google-chrome', $output, $returnVal);
                $chromeInstalled = $returnVal === 0;
            }
            
            // Vérifier les paramètres de téléchargement
            $downloadDir = $this->getParameter('kernel.project_dir') . '/var/downloads';
            $isDownloadDirWritable = is_dir($downloadDir) && is_writable($downloadDir);
            
            return $this->json([
                'success' => true,
                'chromedriver' => [
                    'status' => $chromeDriverStatus ? 'running' : 'not_running',
                    'url' => 'http://localhost:9515',
                    'response' => $chromeDriverStatus ? json_decode($status, true) : null,
                ],
                'chrome' => [
                    'installed' => $chromeInstalled,
                ],
                'download' => [
                    'directory' => $downloadDir,
                    'writable' => $isDownloadDirWritable,
                ],
                'system' => [
                    'os' => PHP_OS,
                    'php_version' => PHP_VERSION,
                ],
                'timestamp' => new \DateTime(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la vérification du statut: ' . $e->getMessage(),
            ]);
        }
    }

    #[Route('/stock-it/test-connection', name: 'stock_it_test_connection')]
    public function testConnection(SeleniumStockItService $stockItService): Response
    {
        // Identifiants en dur pour le test
        $username = 'sub';
        $password = 'sub';
        
        try {
            // Tentative d'authentification avec capture des informations d'URL et de session
            $authInfo = $stockItService->authenticateWithDetails($username, $password);
            
            return $this->json([
                'success' => $authInfo['authenticated'],
                'message' => $authInfo['authenticated'] 
                    ? 'Connexion réussie à Stock-IT' 
                    : 'Échec de la connexion à Stock-IT. Vérifiez vos identifiants.',
                'credentials_used' => [
                    'username' => $username,
                    'password' => $password,
                ],
                'url_info' => [
                    'initial_url' => $authInfo['initial_url'],
                    'after_login_url' => $authInfo['after_login_url'],
                ],
                'session_info' => [
                    'cookies' => $authInfo['cookies'],
                    'page_title' => $authInfo['page_title'],
                ],
                'timestamp' => new \DateTime(),
            ]);
        } catch (\Exception $e) {
            $stockItService->closeDriver();
            
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors du test de connexion: ' . $e->getMessage(),
                'credentials_used' => [
                    'username' => $username,
                    'password' => $password,
                ],
            ]);
        }
    }

}