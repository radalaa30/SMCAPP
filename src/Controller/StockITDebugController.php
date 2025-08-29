<?php
// src/Controller/StockITDebugController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

class StockITDebugController extends AbstractController
{
    private $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    #[Route('/stockit/debug', name: 'app_stockit_debug')]
    public function debug(): Response
    {
        // Paramètres de connexion
        $url = 'https://cdsud.stock-it.fr/webstockit/';
        $username = 'utilisateur_test'; // Remplacez par votre utilisateur de test
        $password = 'mot_de_passe_test'; // Remplacez par votre mot de passe de test
        
        // Pour suivre les étapes pas à pas
        $steps = [];
        
        // ÉTAPE 1 : Aller à l'URL de base
        $steps[] = [
            'title' => 'Étape 1 : Accès à l\'URL de base',
            'url' => $url,
            'description' => 'Tentative d\'accès à l\'URL de base du site.'
        ];
        
        // Utiliser CURL avec les options appropriées pour détecter la redirection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false); // Recevoir le body aussi
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Ne pas suivre les redirections automatiquement
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Désactiver la vérification SSL pour le test
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout en secondes
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36'); // User-agent

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        // Ajouter les détails de la requête initiale
        $steps[0]['httpCode'] = $httpCode;
        $steps[0]['curlError'] = $error;
        $steps[0]['responseInfo'] = curl_getinfo($ch);
        
        // Extraire l'URL de redirection si présente
        $redirectUrl = null;
        $sessionId = null;

        // Debug - Extraire l'en-tête complet
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        $steps[0]['headers'] = $header;
        
        // Vérifier si c'est une redirection (codes 301, 302, 303, 307, 308)
        if ($httpCode >= 300 && $httpCode < 400) {
            if (preg_match('/Location: (.*?)[\r\n]/i', $header, $matches)) {
                $redirectUrl = trim($matches[1]);
                
                // Si l'URL ne commence pas par http, la rendre absolue
                if (strpos($redirectUrl, 'http') !== 0) {
                    $parsedUrl = parse_url($url);
                    $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                    if (strpos($redirectUrl, '/') === 0) {
                        $redirectUrl = $baseUrl . $redirectUrl;
                    } else {
                        $redirectUrl = $baseUrl . '/' . $redirectUrl;
                    }
                }
                
                // Extraire l'ID de session s'il est présent
                if (preg_match('/\(S\((.*?)\)\)/', $redirectUrl, $sessionMatches)) {
                    $sessionId = '(S(' . $sessionMatches[1] . '))';
                }
                
                $steps[0]['redirectDetected'] = true;
                $steps[0]['redirectUrl'] = $redirectUrl;
                $steps[0]['sessionId'] = $sessionId;
            } else {
                $steps[0]['redirectDetected'] = false;
                $steps[0]['redirectHeaderMissing'] = true;
            }
        } else {
            $steps[0]['redirectDetected'] = false;
            
            // Si aucune redirection n'est détectée via les en-têtes, essayons d'analyser le corps de la page
            // pour voir s'il y a une redirection via meta refresh ou JavaScript
            if (strpos($body, '<meta http-equiv="refresh"') !== false || 
                strpos($body, 'window.location') !== false) {
                $steps[0]['possibleClientSideRedirect'] = true;
                $steps[0]['bodyExcerpt'] = substr($body, 0, 1000) . '...'; // Montrer un extrait du corps
            }
        }
        
        curl_close($ch);
        
        // Si aucune redirection n'est détectée via CURL, essayons avec Puppeteer directement
        if (!$redirectUrl) {
            $steps[] = [
                'title' => 'Étape 2 : Redirection non détectée via CURL',
                'description' => 'Aucune redirection n\'a été détectée via les en-têtes HTTP. Essayons avec Puppeteer.',
            ];
            
            // Utilisons Puppeteer pour naviguer vers l'URL et capturer la redirection
            $nodePath = $this->findNodePath();
            $scriptPath = $this->getParameter('kernel.project_dir') . '/node_scripts/detect-redirect.js';
            
            // Si le script n'existe pas, créons-le
            if (!file_exists($scriptPath)) {
                $this->createRedirectDetectionScript($scriptPath);
                $steps[] = [
                    'title' => 'Script de détection créé',
                    'description' => 'Un script Node.js de détection de redirection a été créé : ' . $scriptPath,
                ];
            }
            
            if (file_exists($scriptPath) && $nodePath) {
                $params = [
                    $nodePath,
                    $scriptPath,
                    '--url=' . $url
                ];
                
                $process = new Process($params);
                $process->setWorkingDirectory($this->getParameter('kernel.project_dir'));
                $process->setTimeout(60);
                
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    $redirectData = json_decode($output, true);
                    
                    if ($redirectData && isset($redirectData['finalUrl'])) {
                        $redirectUrl = $redirectData['finalUrl'];
                        
                        // Extraire l'ID de session s'il est présent
                        if (preg_match('/\(S\((.*?)\)\)/', $redirectUrl, $sessionMatches)) {
                            $sessionId = '(S(' . $sessionMatches[1] . '))';
                        }
                        
                        $steps[] = [
                            'title' => 'Redirection détectée via Puppeteer',
                            'description' => 'Puppeteer a détecté une redirection',
                            'url' => $redirectUrl,
                            'sessionId' => $sessionId,
                            'redirectData' => $redirectData
                        ];
                    } else {
                        $steps[] = [
                            'title' => 'Aucune redirection détectée via Puppeteer',
                            'description' => 'Puppeteer n\'a pas détecté de redirection',
                            'output' => $output
                        ];
                    }
                } else {
                    $steps[] = [
                        'title' => 'Erreur lors de l\'exécution de Puppeteer',
                        'description' => 'Erreur : ' . $process->getErrorOutput(),
                    ];
                }
            } else {
                $steps[] = [
                    'title' => 'Impossible d\'exécuter la détection via Puppeteer',
                    'description' => 'Node.js ou le script de détection n\'est pas disponible.',
                    'nodePath' => $nodePath,
                    'scriptPath' => $scriptPath,
                    'nodeExists' => $nodePath ? 'Oui' : 'Non',
                    'scriptExists' => file_exists($scriptPath) ? 'Oui' : 'Non'
                ];
            }
        }
        
        // ÉTAPE 2 ou 3 : Redirection vers la page de connexion avec ID de session
        if ($redirectUrl) {
            $stepNumber = count($steps) + 1;
            $steps[] = [
                'title' => 'Étape ' . $stepNumber . ' : Redirection vers la page de connexion',
                'url' => $redirectUrl,
                'sessionId' => $sessionId,
                'description' => 'Le serveur a redirigé vers cette URL qui contient l\'ID de session.'
            ];
            
            // Vérifier si la page de connexion contient le formulaire
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $redirectUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HEADER, false);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36');
            
            $loginPageContent = curl_exec($ch2);
            $loginHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $loginError = curl_error($ch2);
            curl_close($ch2);
            
            // Ajouter les détails de la requête à la page de connexion
            $steps[count($steps) - 1]['httpCode'] = $loginHttpCode;
            $steps[count($steps) - 1]['curlError'] = $loginError;
            
            // Vérifier si la page contient les éléments du formulaire
            $hasUserField = strpos($loginPageContent, 'txtUtilisateur') !== false;
            $hasPasswordField = strpos($loginPageContent, 'Pwd') !== false;
            $hasSubmitButton = strpos($loginPageContent, 'Valider') !== false;
            
            $hasLoginForm = $hasUserField && $hasPasswordField && $hasSubmitButton;
            
            if ($hasLoginForm) {
                $steps[] = [
                    'title' => 'Étape ' . (count($steps) + 1) . ' : Formulaire de connexion détecté',
                    'description' => 'La page contient bien le formulaire de connexion avec les champs suivants :',
                    'formFields' => [
                        'Nom d\'utilisateur' => 'txtUtilisateur',
                        'Mot de passe' => 'Pwd',
                        'Bouton de validation' => 'Valider'
                    ],
                    'formElementsFound' => [
                        'Champ utilisateur' => $hasUserField ? 'Trouvé' : 'Non trouvé',
                        'Champ mot de passe' => $hasPasswordField ? 'Trouvé' : 'Non trouvé',
                        'Bouton de validation' => $hasSubmitButton ? 'Trouvé' : 'Non trouvé'
                    ]
                ];
                
                // La suite du code pour simuler la connexion...
            } else {
                $steps[] = [
                    'title' => 'Étape ' . (count($steps) + 1) . ' : Problème de détection du formulaire',
                    'description' => 'Le formulaire de connexion n\'a pas été correctement détecté sur la page.',
                    'formElementsFound' => [
                        'Champ utilisateur' => $hasUserField ? 'Trouvé' : 'Non trouvé',
                        'Champ mot de passe' => $hasPasswordField ? 'Trouvé' : 'Non trouvé',
                        'Bouton de validation' => $hasSubmitButton ? 'Trouvé' : 'Non trouvé'
                    ],
                    'contentExcerpt' => substr($loginPageContent, 0, 1000) . '...'
                ];
            }
        } else {
            $steps[] = [
                'title' => 'Erreur',
                'description' => "Aucune redirection n'a été détectée depuis l'URL de base. Vérifiez les détails ci-dessus pour comprendre pourquoi."
            ];
        }
        
        // Afficher les résultats
        return $this->render('stock_it_debug/debug_steps.html.twig', [  
            'steps' => $steps
        ]);
    }
    
    /**
     * Trouve le chemin vers l'exécutable Node.js
     */
    private function findNodePath(): ?string
    {
        $possiblePaths = [
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            'C:\\nodejs\\node.exe',
            $this->getParameter('kernel.project_dir') . '\\node\\node.exe',
            '/usr/bin/node',
            '/usr/local/bin/node'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Essayer de trouver via la commande 'where' sur Windows ou 'which' sur Unix
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $process = new Process(['where', 'node']);
        } else {
            $process = new Process(['which', 'node']);
        }
        
        try {
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim($process->getOutput());
                if (file_exists($path)) {
                    return $path;
                }
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs
        }
        
        return null;
    }
    
    /**
     * Crée un script Node.js pour détecter les redirections
     */
    private function createRedirectDetectionScript(string $scriptPath): void
    {
        $script = <<<'JAVASCRIPT'
#!/usr/bin/env node

const puppeteer = require('puppeteer');
const yargs = require('yargs');

// Définir les arguments de ligne de commande
const argv = yargs
  .option('url', {
    description: 'URL à analyser',
    type: 'string',
    demandOption: true
  })
  .help()
  .alias('help', 'h')
  .argv;

async function detectRedirect() {
  let browser = null;
  
  try {
    // Déterminer le mode headless
    const headlessOption = puppeteer.default?.launch ? true : 'new'; // Compatibilité avec différentes versions
    
    // Lancer le navigateur
    browser = await puppeteer.launch({
      headless: headlessOption,
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
      defaultViewport: { width: 1280, height: 800 }
    });

    // Ouvrir une nouvelle page
    const page = await browser.newPage();
    
    // Tableau pour stocker les redirections
    const redirects = [];
    
    // Écouter les événements de réponse pour détecter les redirections
    page.on('response', response => {
      const status = response.status();
      const url = response.url();
      
      // Les codes 3xx sont des redirections
      if (status >= 300 && status < 400) {
        redirects.push({
          from: url,
          status: status,
          location: response.headers()['location']
        });
      }
    });
    
    // Navigation vers l'URL
    console.error(`Navigating to ${argv.url}`);
    await page.goto(argv.url, {
      waitUntil: 'networkidle2',
      timeout: 30000
    });
    
    // URL finale après toutes les redirections
    const finalUrl = page.url();
    console.error(`Final URL: ${finalUrl}`);
    
    // Extraire l'ID de session s'il est présent
    let sessionId = null;
    const sessionMatch = finalUrl.match(/\(S\((.*?)\)\)/);
    if (sessionMatch) {
      sessionId = sessionMatch[0];
    }
    
    // Capturer une capture d'écran
    await page.screenshot({ path: 'public/screenshots/redirect_detection.png' });
    
    // Analyser le contenu de la page
    const pageContent = await page.content();
    const titleContent = await page.title();
    const hasLoginForm = await page.evaluate(() => {
      return {
        hasUserField: !!document.getElementById('txtUtilisateur'),
        hasPasswordField: !!document.getElementById('Pwd'),
        hasSubmitButton: !!document.getElementById('Valider')
      };
    });
    
    // Retourner les résultats
    const result = {
      initialUrl: argv.url,
      finalUrl: finalUrl,
      redirects: redirects,
      sessionId: sessionId,
      title: titleContent,
      hasLoginForm: hasLoginForm,
      screenshot: 'public/screenshots/redirect_detection.png'
    };
    
    console.log(JSON.stringify(result));
    return result;
    
  } catch (error) {
    console.error(`Error: ${error.message}`);
    console.log(JSON.stringify({
      error: error.message,
      initialUrl: argv.url
    }));
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

// Exécuter la fonction principale
detectRedirect()
  .catch(error => {
    console.error(`Fatal error: ${error.message}`);
    console.log(JSON.stringify({
      error: `Fatal error: ${error.message}`,
      initialUrl: argv.url
    }));
    process.exit(1);
  });
JAVASCRIPT;

        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755); // Rendre le script exécutable
    }
}