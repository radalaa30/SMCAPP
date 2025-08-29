<?php

namespace App\Service;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelect;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class SeleniumStockItService
{
    private $logger;
    private $downloadPath;
    private $driver;
    private $isAuthenticated = false;
    private $isReportLoaded = false;
    private $downloadedFilePath = null;

    public function __construct(LoggerInterface $logger, string $downloadPath = null)
    {
        $this->logger = $logger;
        $this->downloadPath = $downloadPath ?? sys_get_temp_dir();
        
        // Créer le répertoire de téléchargement s'il n'existe pas
        if (!is_dir($this->downloadPath)) {
            mkdir($this->downloadPath, 0777, true);
        }
    }

    /**
     * Initialise le WebDriver avec les options appropriées
     */
    private function initializeDriver(): void
    {
        $options = new ChromeOptions();
        
        // Configurer les options de Chrome
        $options->addArguments([
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
        ]);
        
        // Configurer les préférences pour le téléchargement automatique
        $options->setExperimentalOption('prefs', [
            'download.default_directory' => $this->downloadPath,
            'download.prompt_for_download' => false,
            'download.directory_upgrade' => true,
            'safebrowsing.enabled' => false,
        ]);
        
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        
        // Connexion au serveur WebDriver (assurez-vous que ChromeDriver est en cours d'exécution)
        $this->driver = RemoteWebDriver::create('http://localhost:9515', $capabilities);
        
        // Définir un délai d'attente implicite
        $this->driver->manage()->timeouts()->implicitlyWait(10);
    }

    /**
     * Se connecte au site Stock-IT
     */
    public function authenticate(string $username, string $password): bool
    {
        try {
            $this->logger->info('Tentative de connexion à Stock-IT');
            
            // Initialiser le WebDriver
            $this->initializeDriver();
            
            // Accéder à la page de connexion
            $this->driver->get('https://cdsud.stock-it.fr/webstockit');
            $this->logger->info('Page de connexion chargée');
            
            // Remplir le formulaire de connexion
            $this->driver->findElement(WebDriverBy::name('txtUtilisateur'))->sendKeys($username);
            $this->driver->findElement(WebDriverBy::name('Pwd'))->sendKeys($password);
            
            // Cliquer sur le bouton Valider
            $this->driver->findElement(WebDriverBy::name('Valider'))->click();
            $this->logger->info('Formulaire de connexion soumis');
            
            // Attendre que la page se charge (vérifier un élément qui apparaît après connexion)
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('Menu_Menu1'))
            );
            
            $this->isAuthenticated = true;
            $this->logger->info('Connexion réussie à Stock-IT');
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la connexion: ' . $e->getMessage());
            $this->closeDriver();
            return false;
        }
    }

        /**
 * Se connecte au site Stock-IT et capture les détails d'URL et de session
 */
public function authenticateWithDetails(string $username, string $password): array
{
    $result = [
        'authenticated' => false,
        'initial_url' => '',
        'after_login_url' => '',
        'cookies' => [],
        'page_title' => ''
    ];
    
    try {
        // Initialiser le WebDriver
        if ($this->driver === null) {
            $this->initializeDriver();
        }
        
        // Accéder à la page de connexion
        $this->driver->get('https://cdsud.stock-it.fr/webstockit');
        $this->logger->info('Page de connexion chargée');
        
        // Capturer l'URL initiale
        $result['initial_url'] = $this->driver->getCurrentURL();
        
        // Attendre que la page se charge complètement
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::name('txtUtilisateur'))
        );
        
        // Remplir le formulaire de connexion
        $this->driver->findElement(WebDriverBy::name('txtUtilisateur'))->sendKeys($username);
        $this->driver->findElement(WebDriverBy::name('Pwd'))->sendKeys($password);
        
        // Cliquer sur le bouton Valider
        $this->driver->findElement(WebDriverBy::name('Valider'))->click();
        $this->logger->info('Formulaire de connexion soumis');
        
        // Attendre un peu pour la redirection
        sleep(3);
        
        // Capturer l'URL après connexion
        $result['after_login_url'] = $this->driver->getCurrentURL();
        $result['page_title'] = $this->driver->getTitle();
        
        // Capturer les cookies
        $cookies = $this->driver->manage()->getCookies();
        foreach ($cookies as $cookie) {
            $result['cookies'][] = [
                'name' => $cookie->getName(),
                'value' => $cookie->getValue(),
                'domain' => $cookie->getDomain(),
                'path' => $cookie->getPath(),
                'expiry' => $cookie->getExpiry()
            ];
        }
        
        // Vérifier si l'authentification a réussi
        try {
            // Attendre qu'un élément indiquant une connexion réussie apparaisse
            $this->driver->wait(5)->until(
                WebDriverExpectedCondition::or(
                    WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('Menu_Menu1')),
                    WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath("//a[contains(text(), 'Editions')]"))
                )
            );
            
            $result['authenticated'] = true;
            $this->isAuthenticated = true;
            $this->logger->info('Connexion réussie à Stock-IT');
        } catch (\Exception $e) {
            $result['authenticated'] = false;
            $this->logger->error('Échec de l\'authentification: ' . $e->getMessage());
        }
        
        // Fermer le driver
        $this->closeDriver();
        
        return $result;
    } catch (\Exception $e) {
        $this->logger->error('Erreur lors de l\'authentification: ' . $e->getMessage());
        $this->closeDriver();
        throw $e;
    }
}

    /**
     * Cliquer sur le menu Editions
     */
    public function clickEditions(): bool
    {
        if (!$this->isAuthenticated) {
            $this->logger->error('Vous devez d\'abord vous authentifier');
            return false;
        }
        
        try {
            $this->logger->info('Tentative de clic sur le menu Editions');
            
            // Trouver et cliquer sur le menu Editions
            // Méthode 1: Par identifiant de menu
            try {
                $this->driver->executeScript("__doPostBack('Menu$Menu1','Editions')");
                $this->logger->info('Clic sur Editions via script JavaScript');
            } catch (\Exception $e) {
                // Méthode 2: Par texte de lien
                try {
                    $this->driver->findElement(
                        WebDriverBy::xpath("//a[contains(text(), 'Editions')]")
                    )->click();
                    $this->logger->info('Clic sur Editions via lien texte');
                } catch (\Exception $e2) {
                    // Méthode 3: Par classe CSS et texte
                    $this->driver->findElement(
                        WebDriverBy::cssSelector(".Menu_Menu1_1:contains('Editions')")
                    )->click();
                    $this->logger->info('Clic sur Editions via sélecteur CSS');
                }
            }
            
            // Attendre que la page Editions se charge
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('ArbreRapport_TreeView1'))
            );
            
            $this->logger->info('Navigation vers Editions réussie');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du clic sur Editions: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cliquer sur le rapport CDSUD_SUIVI_PREPARATION_DU_JOUR
     */
    public function clickReport(): bool
    {
        if (!$this->isAuthenticated) {
            $this->logger->error('Vous devez d\'abord vous authentifier et accéder à la page Editions');
            return false;
        }
        
        try {
            $this->logger->info('Tentative de clic sur le rapport CDSUD_SUIVI_PREPARATION_DU_JOUR');
            
            // Trouver et cliquer sur le rapport dans l'arborescence
            try {
                // Méthode 1: Par XPath contenant le texte exact
                $this->driver->findElement(
                    WebDriverBy::xpath("//a[contains(text(), 'CDSUD_SUIVI_PREPARATION_DU_JOUR')]")
                )->click();
            } catch (\Exception $e) {
                // Méthode 2: Par attribut de nœud dans l'arborescence
                $this->driver->executeScript("__doPostBack('ArbreRapport$TreeView1','s3\\\\2')");
            }
            
            // Attendre que la page du rapport se charge (vérifier la présence du bouton Imprimer)
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('Imprimer'))
            );
            
            $this->isReportLoaded = true;
            $this->logger->info('Navigation vers le rapport réussie');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du clic sur le rapport: ' . $e->getMessage());
            return false;
        }
    }
    private function startChromeDriver(): void
{
    try {
        // Vérifier si ChromeDriver est déjà en cours d'exécution
        $context = stream_context_create(['http' => ['timeout' => 3]]);
        $status = @file_get_contents('http://localhost:9515/status', false, $context);
        
        if ($status !== false) {
            $this->logger->info('ChromeDriver est déjà en cours d\'exécution');
            return;
        }
    } catch (\Exception $e) {
        // ChromeDriver n'est pas en cours d'exécution, nous allons le démarrer
    }
    
    $this->logger->info('Démarrage de ChromeDriver...');
    
    // Sur Windows, utilisez exec pour démarrer chromedriver en arrière-plan
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Rechercher dans vendor
        $possiblePaths = [
            $this->getParameter('kernel.project_dir') . '/vendor/bin/chromedriver.exe',
            $this->getParameter('kernel.project_dir') . '/vendor/lmc/steward-chromedriver/bin/chromedriver.exe'
        ];
        
        $chromeDriverPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $chromeDriverPath = $path;
                break;
            }
        }
        
        if (!$chromeDriverPath) {
            // Rechercher dans le PATH
            $chromeDriverPath = 'chromedriver.exe';
        }
        
        // Exécuter en arrière-plan
        pclose(popen("start /B " . $chromeDriverPath, "r"));
        $this->logger->info('ChromeDriver démarré avec la commande: ' . $chromeDriverPath);
    } else {
        // Sur Linux/Mac
        exec('chromedriver > /dev/null 2>&1 &');
        $this->logger->info('ChromeDriver démarré avec la commande: chromedriver');
    }
    
    // Attendre que ChromeDriver soit prêt
    $maxWait = 10;
    $waited = 0;
    
    while ($waited < $maxWait) {
        try {
            $context = stream_context_create(['http' => ['timeout' => 1]]);
            $status = @file_get_contents('http://localhost:9515/status', false, $context);
            
            if ($status !== false) {
                $this->logger->info('ChromeDriver démarré avec succès');
                return;
            }
        } catch (\Exception $e) {
            // Continuer à attendre
        }
        
        sleep(1);
        $waited++;
    }
    
    $this->logger->error('Impossible de démarrer ChromeDriver après ' . $maxWait . ' secondes');
}

    /**
     * Cliquer sur le bouton Imprimer et attendre le téléchargement du fichier Excel
     */
    public function clickImprimer(string $localPath): bool
    {
        if (!$this->isAuthenticated || !$this->isReportLoaded) {
            $this->logger->error('Vous devez d\'abord vous authentifier, accéder à la page Editions et sélectionner le rapport');
            return false;
        }
        
        try {
            $this->logger->info('Tentative de clic sur le bouton Imprimer');
            
            // Vérifier que le bouton Imprimer est présent et visible
            $printButton = $this->driver->findElement(WebDriverBy::id('Imprimer'));
            
            if (!$printButton->isDisplayed() || !$printButton->isEnabled()) {
                $this->logger->error('Le bouton Imprimer n\'est pas visible ou activé');
                return false;
            }
            
            // Obtenir la liste des fichiers dans le répertoire de téléchargement avant le clic
            $filesBefore = scandir($this->downloadPath);
            
            // Cliquer sur le bouton Imprimer
            $printButton->click();
            $this->logger->info('Clic sur le bouton Imprimer effectué');
            
            // Attendre que le téléchargement commence et se termine (max 30 secondes)
            $downloadedFile = null;
            $maxWaitTime = 30;
            $waited = 0;
            
            while ($waited < $maxWaitTime) {
                sleep(1);
                $waited++;
                
                // Vérifier les nouveaux fichiers dans le répertoire de téléchargement
                $filesAfter = scandir($this->downloadPath);
                $newFiles = array_diff($filesAfter, $filesBefore);
                
                // Chercher les fichiers Excel parmi les nouveaux fichiers
                foreach ($newFiles as $file) {
                    if (strpos($file, '.xls') !== false && !is_dir($this->downloadPath . '/' . $file)) {
                        $downloadedFile = $this->downloadPath . '/' . $file;
                        break 2; // Sortir des deux boucles
                    }
                }
                
                $this->logger->info("Attente du téléchargement... ($waited/$maxWaitTime)");
            }
            
            if ($downloadedFile === null) {
                $this->logger->error('Aucun fichier Excel n\'a été téléchargé dans le délai imparti');
                return false;
            }
            
            $this->logger->info('Fichier Excel téléchargé: ' . $downloadedFile);
            
            // Déplacer/copier le fichier vers l'emplacement souhaité
            if (!copy($downloadedFile, $localPath)) {
                $this->logger->error('Impossible de copier le fichier vers: ' . $localPath);
                return false;
            }
            
            $this->downloadedFilePath = $localPath;
            $this->logger->info('Fichier Excel sauvegardé à: ' . $localPath);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du clic sur Imprimer: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retourne le chemin du fichier téléchargé
     */
    public function getDownloadedFilePath(): ?string
    {
        return $this->downloadedFilePath;
    }

    /**
     * Ferme le WebDriver et libère les ressources
     */
    public function closeDriver(): void
    {
        if ($this->driver !== null) {
            $this->driver->quit();
            $this->driver = null;
        }
    }

    /**
     * Assure que le driver est fermé lors de la destruction de l'objet
     */
    public function __destruct()
    {
        $this->closeDriver();
    }
}