<?php
// src/Service/PuppeteerService.php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PuppeteerService
{
    private string $projectDir;
    private LoggerInterface $logger;
    private ParameterBagInterface $params;
    private string $nodePath = 'C:\\Program Files\\nodejs\\node.exe'; // Chemin complet vers Node.js

    public function __construct(
        string $projectDir, 
        LoggerInterface $logger, 
        ParameterBagInterface $params
    ) {
        $this->projectDir = $projectDir;
        $this->logger = $logger;
        $this->params = $params;
    }

    /**
     * Se connecte au site Stock-IT et vérifie si la connexion a réussi
     * 
     * @param string|null $username Nom d'utilisateur (si null, utilise la configuration)
     * @param string|null $password Mot de passe (si null, utilise la configuration)
     * @param bool $headless Exécuter en mode invisible ou non
     * @return array Résultat de la connexion
     */
    public function loginToStockIT(?string $username = null, ?string $password = null, bool $headless = true): array
    {
        try {
            // Utiliser les paramètres de configuration ou valeurs par défaut
            $username = $username ?: $this->getConfigValue('site_username', 'utilisateur_test');
            $password = $password ?: $this->getConfigValue('site_password', 'mot_de_passe_test');
            $url = $this->getConfigValue('site_login_url', 'https://cdsud.stock-it.fr/webstockit/');
            
            // Chemin du script Node.js
            $scriptPath = $this->projectDir . '/node_scripts/stock-it-login.js';
            
            // Vérifier si le script existe
            if (!file_exists($scriptPath)) {
                $this->logger->error('Script Puppeteer non trouvé', ['path' => $scriptPath]);
                throw new \Exception("Le script Puppeteer n'existe pas à l'emplacement $scriptPath");
            }
            
            // Créer le répertoire pour les captures d'écran si nécessaire
            $screenshotsDir = $this->projectDir . '/public/screenshots';
            if (!is_dir($screenshotsDir)) {
                mkdir($screenshotsDir, 0777, true);
            }
            
            // Vérifier si le fichier Node.js existe
            if (!file_exists($this->nodePath)) {
                $this->logger->warning('Node.js non trouvé au chemin spécifié', ['path' => $this->nodePath]);
                
                // Essayer d'autres chemins courants
                $possiblePaths = [
                    'C:\\Program Files\\nodejs\\node.exe',
                    'C:\\Program Files (x86)\\nodejs\\node.exe',
                    'C:\\nodejs\\node.exe',
                    $this->projectDir . '\\node\\node.exe'
                ];
                
                foreach ($possiblePaths as $path) {
                    if (file_exists($path)) {
                        $this->nodePath = $path;
                        $this->logger->info('Node.js trouvé à', ['path' => $path]);
                        break;
                    }
                }
                
                if (!file_exists($this->nodePath)) {
                    throw new \Exception("Node.js n'est pas trouvé sur le système. Veuillez installer Node.js ou spécifier le chemin correct.");
                }
            }
            
            // Préparer les paramètres du script
            $params = [
                $this->nodePath, // Utilise le chemin complet au lieu de simplement 'node'
                $scriptPath,
                '--url=' . $url,
                '--username=' . $username,
                '--password=' . $password,
                '--headless=' . ($headless ? 'true' : 'false')
            ];
            
            $this->logger->info('Commande à exécuter', [
                'nodePath' => $this->nodePath,
                'scriptPath' => $scriptPath
            ]);
            
            // Exécuter le script Node.js
            $process = new Process($params);
            $process->setWorkingDirectory($this->projectDir);
            $process->setTimeout(60); // Timeout en secondes
            
            $this->logger->info('Démarrage du processus de connexion à Stock-IT', [
                'url' => $url,
                'username' => $username
                // Ne pas logger le mot de passe pour des raisons de sécurité
            ]);
            
            $process->run();
            
            // Vérifier si le processus a réussi
            if (!$process->isSuccessful()) {
                $this->logger->error('Échec du processus', [
                    'exitCode' => $process->getExitCode(),
                    'output' => $process->getOutput(),
                    'errorOutput' => $process->getErrorOutput()
                ]);
                throw new ProcessFailedException($process);
            }
            
            $output = $process->getOutput();
            $this->logger->debug('Sortie du processus', ['output' => $output]);
            
            // Vérifier si la sortie est vide
            if (empty(trim($output))) {
                $this->logger->error('La sortie du script est vide');
                throw new \Exception('Le script n\'a produit aucune sortie. Vérifiez le script Node.js.');
            }
            
            // Essayer de trouver un JSON valide dans la sortie
            if (preg_match('/{.*}/s', $output, $matches)) {
                $jsonContent = $matches[0];
                $result = json_decode($jsonContent, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('Erreur de décodage JSON avec expression régulière', [
                        'error' => json_last_error_msg(),
                        'match' => $jsonContent
                    ]);
                    throw new \Exception('Impossible de parser la sortie JSON après extraction: ' . json_last_error_msg());
                }
            } else {
                // Essayer de parser la sortie complète
                $result = json_decode($output, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('Erreur de décodage JSON', [
                        'error' => json_last_error_msg(),
                        'output' => $output
                    ]);
                    
                    // Créer une sortie de secours pour éviter l'échec complet
                    $result = [
                        'success' => false,
                        'message' => 'Erreur de format JSON: ' . json_last_error_msg(),
                        'raw_output' => $output
                    ];
                }
            }
            
            // Si aucun résultat valide n'a été obtenu, créer un résultat de secours
            if (!is_array($result)) {
                $result = [
                    'success' => false,
                    'message' => 'Sortie invalide du script',
                    'raw_output' => $output
                ];
            }
            
            // Journaliser le résultat
            if (isset($result['success']) && $result['success']) {
                $this->logger->info('Connexion à Stock-IT réussie', [
                    'title' => $result['title'] ?? 'N/A',
                    'url' => $result['url'] ?? 'N/A'
                ]);
            } else {
                $this->logger->error('Échec de connexion à Stock-IT', [
                    'message' => $result['message'] ?? 'Raison inconnue'
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->error('Erreur lors de l\'exécution du script Puppeteer', [
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
    }
    
    /**
     * Récupère une valeur de configuration ou utilise une valeur par défaut
     */
    private function getConfigValue(string $key, string $default): string
    {
        try {
            if ($this->params->has($key)) {
                return $this->params->get($key);
            }
        } catch (\Exception $e) {
            $this->logger->warning('Impossible de récupérer le paramètre', [
                'key' => $key, 
                'error' => $e->getMessage()
            ]);
        }
        
        return $default;
    }
    
    /**
     * Change le chemin vers Node.js
     */
    public function setNodePath(string $path): void
    {
        $this->nodePath = $path;
    }
}