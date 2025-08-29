<?php
namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class StockItService
{
    private $logger;
    private $cookieJar;
    private $sessionUrl;
    private $isAuthenticated = false;
    private $isEditionsLoaded = false;
    private $isReportLoaded = false;
    private $downloadedFilePath = null;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->cookieJar = sys_get_temp_dir() . '/stockit_cookies_' . uniqid() . '.txt';
    }
    
    public function __destruct()
    {
        // Nettoyer le fichier de cookies temporaire
        if (file_exists($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
    }
    
    /**
     * Se connecte au site Stock-IT avec affichage des messages de débogage
     */
    public function authenticate(string $username, string $password): bool
    {
        // 1. Afficher les informations initiales
        echo "<h1>Tentative de connexion à Stock-IT</h1>";
        echo "<p>Utilisateur: $username</p>";
        echo "<p>Fichier de cookies: {$this->cookieJar}</p>";
        
        // 2. Initialiser cURL
        $ch = curl_init();
        if (!$ch) {
            echo "<p style='color:red'>ERREUR: Impossible d'initialiser cURL</p>";
            return false;
        }
        echo "<p>cURL initialisé avec succès</p>";
        
        // 3. Configurer cURL pour obtenir la page de login
        curl_setopt($ch, CURLOPT_URL, 'https://cdsud.stock-it.fr/webstockit');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36');
        
        echo "<p>Options cURL configurées pour la page de login</p>";
        
        // 4. Exécuter la requête pour obtenir la page de login
        $response = curl_exec($ch);
        
        if ($response === false) {
            echo "<p style='color:red'>ERREUR cURL: " . curl_error($ch) . "</p>";
            curl_close($ch);
            return false;
        }
        
        // 5. Extraire les informations de la réponse
        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        $this->sessionUrl = $info['url'];
        echo "<p>URL de session: {$this->sessionUrl}</p>";
        echo "<p>Code HTTP: {$info['http_code']}</p>";
        
        // 6. Enregistrer la page de login pour débogage
        $loginPage = sys_get_temp_dir() . '/stockit_login_page.html';
        file_put_contents($loginPage, $body);
        echo "<p>Page de login enregistrée dans: $loginPage</p>";
        
        // 7. Extraire les champs cachés du formulaire
        $formFields = [];
        preg_match_all('/<input[^>]*type="hidden"[^>]*name="([^"]*)"[^>]*value="([^"]*)"[^>]*>/i', $body, $matches, PREG_SET_ORDER);
        
        echo "<h2>Champs cachés trouvés:</h2>";
        echo "<ul>";
        foreach ($matches as $match) {
            $formFields[$match[1]] = $match[2];
            echo "<li><strong>{$match[1]}</strong>: {$match[2]}</li>";
        }
        echo "</ul>";
        
        // 8. Extraire l'action du formulaire
        preg_match('/<form[^>]*action="([^"]*)"[^>]*>/i', $body, $formMatch);
        $formAction = isset($formMatch[1]) ? $formMatch[1] : './Login.aspx';
        echo "<p>Action du formulaire: $formAction</p>";
        
        // 9. Ajouter les identifiants de connexion
        $formFields['txtUtilisateur'] = $username;
        $formFields['Pwd'] = $password;
        $formFields['Valider'] = 'Valider';
        
        echo "<p>Identifiants ajoutés aux données du formulaire</p>";
        
        // 10. Construire l'URL complète du formulaire
        $fullFormUrl = $this->buildFormUrl($this->sessionUrl, $formAction);
        echo "<p>URL complète du formulaire: $fullFormUrl</p>";
        
        // 11. Soumettre le formulaire
        echo "<h2>Soumission du formulaire...</h2>";
        
        curl_setopt($ch, CURLOPT_URL, $fullFormUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formFields));
        curl_setopt($ch, CURLOPT_REFERER, $this->sessionUrl);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            echo "<p style='color:red'>ERREUR lors de la soumission du formulaire: " . curl_error($ch) . "</p>";
            curl_close($ch);
            return false;
        }
        
        // 12. Extraire les informations de la réponse
        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        $redirectUrl = $info['url'];
        echo "<p>URL après soumission: $redirectUrl</p>";
        echo "<p>Code HTTP: {$info['http_code']}</p>";
        
        // Mettre à jour l'URL de session
        $this->sessionUrl = $redirectUrl;
        
        // 13. Enregistrer la page pour débogage
        $afterLoginPage = sys_get_temp_dir() . '/stockit_after_login.html';
        file_put_contents($afterLoginPage, $body);
        echo "<p>Page après login enregistrée dans: $afterLoginPage</p>";
        
        // 14. Vérifier si on est toujours sur la page de login
        if (strpos($redirectUrl, 'Login.aspx') !== false) {
            echo "<p style='color:red'>ERREUR: Toujours sur la page de login après soumission, authentification échouée</p>";
            
            // Chercher des messages d'erreur potentiels
            if (preg_match('/<span[^>]*class="error"[^>]*>(.*?)<\/span>/is', $body, $errorMatch)) {
                echo "<p style='color:red'>Message d'erreur trouvé: " . trim(strip_tags($errorMatch[1])) . "</p>";
            }
            
            curl_close($ch);
            return false;
        }
        
        // 15. Vérifier si l'authentification a réussi
        $successIndicators = ['Déconnexion', 'Bienvenue', 'Mon compte', 'Menu principal', 'Editions'];
        foreach ($successIndicators as $indicator) {
            if (strpos($body, $indicator) !== false) {
                echo "<h1 style='color:green'>SUCCÈS: Authentification réussie! Indicateur trouvé: $indicator</h1>";
                $this->isAuthenticated = true;
                curl_close($ch);
                return true;
            }
        }
        
        echo "<p style='color:red'>ÉCHEC: Aucun indicateur de succès trouvé, authentification probablement échouée</p>";
        echo "<p>Vérifiez le fichier $afterLoginPage pour plus de détails</p>";
        
        curl_close($ch);
        return false;
    }
    
    /**
     * Cliquer sur le menu Editions après l'authentification
     */
    public function clickEditions(): bool
    {
        if (!$this->isAuthenticated) {
            echo "<p style='color:red'>ERREUR: Vous devez d'abord vous authentifier</p>";
            return false;
        }
        
        echo "<h1>Tentative de clic sur le menu Editions</h1>";
        echo "<p>URL de session: {$this->sessionUrl}</p>";
        
        // 1. Initialiser cURL
        $ch = curl_init();
        if (!$ch) {
            echo "<p style='color:red'>ERREUR: Impossible d'initialiser cURL</p>";
            return false;
        }
        echo "<p>cURL initialisé avec succès</p>";
        
        // 2. Configurer cURL pour obtenir la page d'accueil
        curl_setopt($ch, CURLOPT_URL, $this->sessionUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36');
        
        echo "<p>Options cURL configurées pour la page d'accueil</p>";
        
        // 3. Exécuter la requête pour obtenir la page d'accueil
        $response = curl_exec($ch);
        
        if ($response === false) {
            echo "<p style='color:red'>ERREUR cURL: " . curl_error($ch) . "</p>";
            curl_close($ch);
            return false;
        }
        
        // 4. Extraire les informations de la réponse
        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $body = substr($response, $headerSize);
        
        echo "<p>Code HTTP: {$info['http_code']}</p>";
        
        // 5. Enregistrer la page d'accueil pour débogage
        $homePage = sys_get_temp_dir() . '/stockit_home_page.html';
        file_put_contents($homePage, $body);
        echo "<p>Page d'accueil enregistrée dans: $homePage</p>";
        
        // 6. Rechercher le lien ou menu Editions
        echo "<h2>Recherche du menu Editions...</h2>";
        
        // Rechercher les liens dans la page
        preg_match_all('/<a[^>]*class="[^"]*Menu[^"]*"[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', $body, $menuLinks, PREG_SET_ORDER);
        
        echo "<h3>Liens de menu trouvés:</h3>";
        echo "<ul>";
        foreach ($menuLinks as $link) {
            $href = $link[1];
            $text = trim(strip_tags($link[2]));
            echo "<li><strong>$text</strong>: $href</li>";
            
            // Si c'est le lien Editions
            if (strpos($text, 'Edition') !== false || $text == 'Editions') {
                echo "<p style='color:green'>Menu Editions trouvé! Href = $href</p>";
            }
        }
        echo "</ul>";
        
        // 7. Extraire les champs cachés pour le postback
        $formFields = [];
        preg_match_all('/<input[^>]*type="hidden"[^>]*name="([^"]*)"[^>]*value="([^"]*)"[^>]*>/i', $body, $matches, PREG_SET_ORDER);
        
        echo "<h3>Champs cachés pour le postback:</h3>";
        echo "<ul>";
        foreach ($matches as $match) {
            $formFields[$match[1]] = $match[2];
            echo "<li><strong>{$match[1]}</strong>: {$match[2]}</li>";
        }
        echo "</ul>";
        
        // 8. Préparer le postback pour cliquer sur Editions
        echo "<h2>Préparation du postback pour Editions...</h2>";
        
        $formFields['__EVENTTARGET'] = 'Menu$Menu1';
        $formFields['__EVENTARGUMENT'] = 'Editions';
        
        echo "<p>Données du postback configurées</p>";
        
        // 9. Exécuter le postback
        echo "<h2>Exécution du postback...</h2>";
        
        curl_setopt($ch, CURLOPT_URL, $this->sessionUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formFields));
        curl_setopt($ch, CURLOPT_REFERER, $this->sessionUrl);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            echo "<p style='color:red'>ERREUR lors du postback: " . curl_error($ch) . "</p>";
            curl_close($ch);
            return false;
        }
        
        // 10. Extraire les informations de la réponse
        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $body = substr($response, $headerSize);
        $currentUrl = $info['url'];
        
        echo "<p>Code HTTP: {$info['http_code']}</p>";
        echo "<p>URL après postback: $currentUrl</p>";
        
        // Mettre à jour l'URL de session
        $this->sessionUrl = $currentUrl;
        
        // 11. Enregistrer la page Editions pour débogage
        $editionsPage = sys_get_temp_dir() . '/stockit_editions_page.html';
        file_put_contents($editionsPage, $body);
        echo "<p>Page Editions enregistrée dans: $editionsPage</p>";
        
        // 12. Vérifier si la navigation a réussi
        if (strpos($body, 'ArbreRapport') !== false || 
            strpos($body, 'TreeView') !== false || 
            strpos($body, 'CDSUD_SUIVI_PREPARATION') !== false) {
            echo "<h1 style='color:green'>SUCCÈS: Navigation vers Editions réussie!</h1>";
            
            // Identifier les rapports disponibles
            preg_match_all('/<a[^>]*class="[^"]*Arbre[^"]*"[^>]*>(.*?)<\/a>/is', $body, $reports, PREG_SET_ORDER);
            
            if (count($reports) > 0) {
                echo "<h3>Rapports disponibles:</h3>";
                echo "<ul>";
                foreach ($reports as $report) {
                    $reportName = trim(strip_tags($report[1]));
                    echo "<li>$reportName</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>Aucun rapport trouvé dans la page</p>";
            }
            
            // Marquer que nous sommes sur la page Editions
            $this->isEditionsLoaded = true;
            curl_close($ch);
            return true;
        } else {
            echo "<p style='color:red'>ÉCHEC: La page ne semble pas être la page Editions</p>";
            echo "<p>Vérifiez le fichier $editionsPage pour plus de détails</p>";
            
            curl_close($ch);
            return false;
        }
    }
    
    /**
     * Cliquer sur le rapport CDSUD_SUIVI_PREPARATION_DU_JOUR
     */
    public function clickReport(): bool
    {
        if (!$this->isAuthenticated || !$this->isEditionsLoaded) {
            echo "<p style='color:red'>ERREUR: Vous devez d'abord vous authentifier et accéder à la page Editions</p>";
            return false;
        }
        
        echo "<h1>Tentative de clic sur le rapport CDSUD_SUIVI_PREPARATION_DU_JOUR</h1>";
        echo "<p>URL de session: {$this->sessionUrl}</p>";
        
        // 1. Initialiser cURL
        $ch = curl_init();
        if (!$ch) {
            echo "<p style='color:red'>ERREUR: Impossible d'initialiser cURL</p>";
            return false;
        }
        echo "<p>cURL initialisé avec succès</p>";
        
        // 2. Configurer cURL pour obtenir la page Editions actuelle
        curl_setopt($ch, CURLOPT_URL, $this->sessionUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36');
        
        echo "<p>Options cURL configurées pour la page Editions</p>";
        
        // 3. Exécuter la requête pour obtenir la page Editions actuelle
        $response = curl_exec($ch);
        
        if ($response === false) {
            echo "<p style='color:red'>ERREUR cURL: " . curl_error($ch) . "</p>";
            curl_close($ch);
            return false;
        }
        
        // 4. Extraire les informations de la réponse
        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $body = substr($response, $headerSize);
        
        echo "<p>Code HTTP: {$info['http_code']}</p>";
        
        // 5. Rechercher le rapport dans la page
        echo "<h2>Recherche du rapport CDSUD_SUIVI_PREPARATION_DU_JOUR...</h2>";
        
        // Chercher tous les liens dans l'arbre des rapports
        preg_match_all('/<a[^>]*id="([^"]*)"[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', $body, $reportLinks, PREG_SET_ORDER);
        
        $reportId = '';
        $reportHref = '';
        $reportEventTarget = '';
        $reportEventArgument = '';
        
        echo "<h3>Liens trouvés dans l'arbre des rapports:</h3>";
        echo "<ul>";
        foreach ($reportLinks as $link) {
            $id = $link[1];
            $href = $link[2];
            $text = trim(strip_tags($link[3]));
            echo "<li><strong>$text</strong> (ID: $id): $href</li>";
            
            // Si c'est notre rapport
            if (strpos($text, 'CDSUD_SUIVI_PREPARATION_DU_JOUR') !== false) {
                echo "<p style='color:green'>Rapport trouvé! ID = $id, Href = $href</p>";
                $reportId = $id;
                $reportHref = $href;
                
                // Extraire les paramètres du postback depuis le href
                if (preg_match('/javascript:__doPostBack\(\'([^\']+)\',\'([^\']+)\'\)/', $href, $matches)) {
                    $reportEventTarget = $matches[1];
                    $reportEventArgument = $matches[2];
                    echo "<p>EventTarget: $reportEventTarget, EventArgument: $reportEventArgument</p>";
                }
            }
        }
        echo "</ul>";
        
        // Si nous n'avons pas trouvé le rapport explicitement, essayer avec une méthode alternative
        if (empty($reportEventTarget)) {
            echo "<p>Rapport non trouvé par lien direct, tentative alternative...</p>";
            $reportEventTarget = 'ArbreRapport$TreeView1';
            $reportEventArgument = 's3\\2';
            echo "<p>Utilisation des valeurs par défaut: EventTarget: $reportEventTarget, EventArgument: $reportEventArgument</p>";
        }
        
        // 6. Extraire les champs cachés pour le postback
        $formFields = [];
        preg_match_all('/<input[^>]*type="hidden"[^>]*name="([^"]*)"[^>]*value="([^"]*)"[^>]*>/i', $body, $matches, PREG_SET_ORDER);
        
        echo "<h3>Champs cachés pour le postback:</h3>";
        echo "<ul>";
        foreach ($matches as $match) {
            $formFields[$match[1]] = $match[2];
            echo "<li><strong>{$match[1]}</strong>: {$match[2]}</li>";
        }
        echo "</ul>";
        
        // 7. Préparer le postback pour cliquer sur le rapport
        echo "<h2>Préparation du postback pour le rapport...</h2>";
        
        $formFields['__EVENTTARGET'] = $reportEventTarget;
        $formFields['__EVENTARGUMENT'] = $reportEventArgument;
        
        echo "<p>Données du postback configurées</p>";
        
        // 8. Exécuter le postback
        echo "<h2>Exécution du postback...</h2>";
        
        curl_setopt($ch, CURLOPT_URL, $this->sessionUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formFields));
        curl_setopt($ch, CURLOPT_REFERER, $this->sessionUrl);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            echo "<p style='color:red'>ERREUR lors du postback: " . curl_error($ch) . "</p>";
            curl_close($ch);
            return false;
        }
        
        // 9. Extraire les informations de la réponse
        $info = curl_getinfo($ch);
        $headerSize = $info['header_size'];
        $body = substr($response, $headerSize);
        $currentUrl = $info['url'];
        
        echo "<p>Code HTTP: {$info['http_code']}</p>";
        echo "<p>URL après postback: $currentUrl</p>";
        
        // Mettre à jour l'URL de session
        $this->sessionUrl = $currentUrl;
        
        // 10. Enregistrer la page du rapport pour débogage
        $reportPage = sys_get_temp_dir() . '/stockit_report_page.html';
        file_put_contents($reportPage, $body);
        echo "<p>Page du rapport enregistrée dans: $reportPage</p>";
        
        // 11. Vérifier si la navigation a réussi - MODIFICATION ICI
        if (strpos($body, 'name="Imprimer"') !== false || strpos($body, 'id="Imprimer"') !== false) {
            echo "<h1 style='color:green'>SUCCÈS: Navigation vers le rapport réussie!</h1>";
            
            // Rechercher le bouton Imprimer avec une expression régulière plus précise
            if (preg_match('/<input[^>]*(?:id="Imprimer"|name="Imprimer")[^>]*>/i', $body)) {
                echo "<p style='color:green'>Bouton Imprimer trouvé sur la page!</p>";
                $this->isReportLoaded = true;
            } else {
                echo "<p style='color:orange'>Attention: Bouton Imprimer non trouvé avec l'expression régulière précise. Mais un élément avec name ou id 'Imprimer' semble exister.</p>";
                $this->isReportLoaded = true; // On continue quand même
            }
            
            curl_close($ch);
            return true;
        } else {
            echo "<p style='color:red'>ÉCHEC: La page ne semble pas être la page du rapport (bouton Imprimer non trouvé)</p>";
            echo "<p>Vérifiez le fichier $reportPage pour plus de détails</p>";
            
            curl_close($ch);
            return false;
        }
    }
    
    /**
     * Cliquer sur le bouton Imprimer et télécharger le fichier Excel
     */
    public function clickImprimer(string $localPath): bool
{
    if (!$this->isAuthenticated || !$this->isReportLoaded) {
        echo "<p style='color:red'>ERREUR: Vous devez d'abord vous authentifier, accéder à la page Editions et sélectionner le rapport</p>";
        return false;
    }
    
    echo "<h1>Tentative de clic sur le bouton Imprimer avec méthodes multiples</h1>";
    echo "<p>URL de session: {$this->sessionUrl}</p>";
    
    // Essayer plusieurs méthodes jusqu'à ce qu'une fonctionne
    if ($this->tryMethodStandard($localPath)) {
        return true;
    }
    
    if ($this->tryMethodEventTarget($localPath)) {
        return true;
    }
    
    if ($this->tryMethodDirectURL($localPath)) {
        return true;
    }
    
    echo "<p style='color:red'>ÉCHEC: Toutes les méthodes pour obtenir le fichier Excel ont échoué</p>";
    return false;
}

/**
 * Méthode 1: Approche standard en utilisant le nom du bouton
 */
private function tryMethodStandard(string $localPath): bool
{
    echo "<h2>Méthode 1: Approche standard avec nom du bouton</h2>";
    
    // Initialiser cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->sessionUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36');
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        echo "<p style='color:red'>ERREUR cURL: " . curl_error($ch) . "</p>";
        curl_close($ch);
        return false;
    }
    
    $info = curl_getinfo($ch);
    $headerSize = $info['header_size'];
    $body = substr($response, $headerSize);
    
    // Extraire tous les champs cachés
    $formFields = [];
    preg_match_all('/<input[^>]*type="hidden"[^>]*name="([^"]*)"[^>]*value="([^"]*)"[^>]*>/i', $body, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $formFields[$match[1]] = $match[2];
    }
    
    // Ajouter le bouton Imprimer en tant que champ
    $formFields['Imprimer'] = 'Imprimer';
    
    // Supprimer __EVENTTARGET et __EVENTARGUMENT si présents
    if (isset($formFields['__EVENTTARGET'])) unset($formFields['__EVENTTARGET']);
    if (isset($formFields['__EVENTARGUMENT'])) unset($formFields['__EVENTARGUMENT']);
    
    echo "<p>Données du postback: " . json_encode($formFields) . "</p>";
    
    // Exécuter le postback
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formFields));
    curl_setopt($ch, CURLOPT_REFERER, $this->sessionUrl);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        echo "<p style='color:red'>ERREUR lors du postback: " . curl_error($ch) . "</p>";
        curl_close($ch);
        return false;
    }
    
    // Vérifier si c'est un fichier Excel
    $info = curl_getinfo($ch);
    $contentType = $info['content_type'];
    $isExcel = $this->isExcelResponse($info, $response);
    
    if (!$isExcel) {
        echo "<p style='color:orange'>Méthode 1 a échoué: Le contenu n'est pas un fichier Excel</p>";
        curl_close($ch);
        return false;
    }
    
    // C'est un fichier Excel, l'enregistrer
    $headerSize = $info['header_size'];
    $body = substr($response, $headerSize);
    
    if (!$this->saveExcelFile($body, $localPath)) {
        curl_close($ch);
        return false;
    }
    
    echo "<p style='color:green'>SUCCÈS: Fichier Excel téléchargé avec la méthode 1</p>";
    curl_close($ch);
    return true;
}

/**
 * Méthode 2: Approche avec __EVENTTARGET
 */
private function tryMethodEventTarget(string $localPath): bool
{
    echo "<h2>Méthode 2: Approche avec __EVENTTARGET</h2>";
    
    // Initialiser cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->sessionUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36');
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        echo "<p style='color:red'>ERREUR cURL: " . curl_error($ch) . "</p>";
        curl_close($ch);
        return false;
    }
    
    $info = curl_getinfo($ch);
    $headerSize = $info['header_size'];
    $body = substr($response, $headerSize);
    
    // Extraire tous les champs cachés
    $formFields = [];
    preg_match_all('/<input[^>]*type="hidden"[^>]*name="([^"]*)"[^>]*value="([^"]*)"[^>]*>/i', $body, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $formFields[$match[1]] = $match[2];
    }
    
    // Définir __EVENTTARGET et __EVENTARGUMENT
    $formFields['__EVENTTARGET'] = 'Imprimer';
    $formFields['__EVENTARGUMENT'] = '';
    
    // Supprimer le champ Imprimer s'il est présent
    if (isset($formFields['Imprimer'])) unset($formFields['Imprimer']);
    
    echo "<p>Données du postback: " . json_encode($formFields) . "</p>";
    
    // Exécuter le postback
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formFields));
    curl_setopt($ch, CURLOPT_REFERER, $this->sessionUrl);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        echo "<p style='color:red'>ERREUR lors du postback: " . curl_error($ch) . "</p>";
        curl_close($ch);
        return false;
    }
    
    // Vérifier si c'est un fichier Excel
    $info = curl_getinfo($ch);
    $isExcel = $this->isExcelResponse($info, $response);
    
    if (!$isExcel) {
        echo "<p style='color:orange'>Méthode 2 a échoué: Le contenu n'est pas un fichier Excel</p>";
        curl_close($ch);
        return false;
    }
    
    // C'est un fichier Excel, l'enregistrer
    $headerSize = $info['header_size'];
    $body = substr($response, $headerSize);
    
    if (!$this->saveExcelFile($body, $localPath)) {
        curl_close($ch);
        return false;
    }
    
    echo "<p style='color:green'>SUCCÈS: Fichier Excel téléchargé avec la méthode 2</p>";
    curl_close($ch);
    return true;
}

/**
 * Méthode 3: Essai avec des URLs directes
 */
private function tryMethodDirectURL(string $localPath): bool
{
    echo "<h2>Méthode 3: Tentative avec des URLs directes</h2>";
    
    // Extraire la base de l'URL
    $parsedUrl = parse_url($this->sessionUrl);
    $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    $path = dirname($parsedUrl['path']);
    
    // Extraire l'ID de session ASP.NET si présent
    $sessionId = '';
    if (preg_match('/\(S\(([a-z0-9]+)\)\)/i', $this->sessionUrl, $matches)) {
        $sessionId = $matches[1];
        echo "<p>ID de session ASP.NET: $sessionId</p>";
    }
    
    // Construire différentes variantes d'URL possibles
    $urls = [
        $this->sessionUrl . '?action=export&format=excel',
        $baseUrl . $path . '/Export.aspx?format=excel&report=CDSUD_SUIVI_PREPARATION_DU_JOUR',
        $baseUrl . $path . '/ExportExcel.aspx?report=CDSUD_SUIVI_PREPARATION_DU_JOUR'
    ];
    
    // Si nous avons un ID de session, ajouter des URLs avec cet ID
    if (!empty($sessionId)) {
        $urls[] = $baseUrl . $path . '/Export.aspx?format=excel&report=CDSUD_SUIVI_PREPARATION_DU_JOUR&session=' . $sessionId;
        $urls[] = $baseUrl . $path . '/(S(' . $sessionId . '))/Export.aspx?format=excel&report=CDSUD_SUIVI_PREPARATION_DU_JOUR';
    }
    
    // Initialiser cURL une seule fois
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36');
    curl_setopt($ch, CURLOPT_REFERER, $this->sessionUrl);
    
    // Essayer chaque URL
    foreach ($urls as $url) {
        echo "<p>Tentative avec URL: $url</p>";
        curl_setopt($ch, CURLOPT_URL, $url);
        
        $response = curl_exec($ch);
        if ($response === false) {
            echo "<p style='color:orange'>ERREUR: " . curl_error($ch) . "</p>";
            continue;
        }
        
        $info = curl_getinfo($ch);
        $isExcel = $this->isExcelResponse($info, $response);
        
        if ($isExcel) {
            $headerSize = $info['header_size'];
            $body = substr($response, $headerSize);
            
            if ($this->saveExcelFile($body, $localPath)) {
                echo "<p style='color:green'>SUCCÈS: Fichier Excel téléchargé avec l'URL: $url</p>";
                curl_close($ch);
                return true;
            }
        } else {
            echo "<p style='color:orange'>Cette URL n'a pas retourné un fichier Excel</p>";
        }
    }
    
    echo "<p style='color:red'>Toutes les URLs ont échoué</p>";
    curl_close($ch);
    return false;
}

/**
 * Vérifie si la réponse est un fichier Excel
 */
private function isExcelResponse(array $info, string $response): bool
{
    $contentType = $info['content_type'];
    $headerSize = $info['header_size'];
    $header = substr($response, 0, $headerSize);
    
    // Vérifier le Content-Type
    $isExcel = strpos($contentType, 'application/vnd.ms-excel') !== false || 
               strpos($contentType, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') !== false ||
               strpos($contentType, 'application/octet-stream') !== false;
    
    // Vérifier aussi le Content-Disposition
    if (preg_match('/Content-Disposition: ([^\r\n]+)/i', $header, $matches)) {
        $contentDisposition = $matches[1];
        echo "<p>Content-Disposition: $contentDisposition</p>";
        
        if (strpos($contentDisposition, '.xls') !== false) {
            $isExcel = true;
        }
    }
    
    return $isExcel;
}

/**
 * Sauvegarde le fichier Excel
 */
private function saveExcelFile(string $content, string $localPath): bool
{
    // Créer le répertoire si nécessaire
    $directory = dirname($localPath);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0777, true)) {
            echo "<p style='color:red'>ERREUR: Impossible de créer le répertoire $directory</p>";
            return false;
        }
        echo "<p>Répertoire créé: $directory</p>";
    }
    
    // Enregistrer le fichier
    if (file_put_contents($localPath, $content) === false) {
        echo "<p style='color:red'>ERREUR: Impossible d'enregistrer le fichier à $localPath</p>";
        return false;
    }
    
    $fileSize = filesize($localPath);
    echo "<p style='color:green'>Fichier Excel enregistré avec succès à: $localPath</p>";
    echo "<p>Taille du fichier: $fileSize octets</p>";
    
    $this->downloadedFilePath = $localPath;
    return true;
}
    
    /**
     * Retourne le chemin du fichier téléchargé
     */
    public function getDownloadedFilePath(): ?string
    {
        return $this->downloadedFilePath;
    }
    
    /**
     * Construit l'URL complète du formulaire en conservant l'ID de session
     */
    private function buildFormUrl(string $baseUrl, string $formAction): string
    {
        // Si l'action commence par http, c'est déjà une URL absolue
        if (strpos($formAction, 'http') === 0) {
            return $formAction;
        }
        
        // Si l'action commence par ./
        if (strpos($formAction, './') === 0) {
            $formAction = substr($formAction, 2);
        }
        
        // Analyser l'URL de base
        $parsedUrl = parse_url($baseUrl);
        
        // Vérifier si l'URL contient un ID de session ASP.NET
        $sessionIdPattern = '/\(S\([a-z0-9]+\)\)/i';
        $sessionId = '';
        
        if (preg_match($sessionIdPattern, $baseUrl, $matches)) {
            $sessionId = $matches[0];
            echo "<p>ID de session ASP.NET trouvé: $sessionId</p>";
        }
        
        // Construire l'URL
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'] ?? 'cdsud.stock-it.fr';
        $path = $parsedUrl['path'] ?? '';
        
        // Obtenir le chemin de base (répertoire)
        $basePath = dirname($path);
        if ($basePath == '/') {
            $basePath = '';
        }
        
        // Si l'action commence par /, c'est une URL absolue par rapport au domaine
        if (strpos($formAction, '/') === 0) {
            $result = "$scheme://$host$formAction";
        } else {
            // Sinon, c'est une URL relative au répertoire courant
            // Insérer l'ID de session si présent
            if ($sessionId && strpos($basePath, $sessionId) === false) {
                // Ajouter l'ID de session après le premier segment de chemin
                $pathSegments = explode('/', trim($basePath, '/'));
                if (count($pathSegments) > 0) {
                    $result = "$scheme://$host/" . $pathSegments[0] . "/$sessionId/$formAction";
                } else {
                    $result = "$scheme://$host/$sessionId/$formAction";
                }
            } else {
                $result = "$scheme://$host$basePath/$formAction";
            }
        }
        
        // Corriger les doubles slashes (sauf dans http://)
        $result = preg_replace('#([^:])//+#', '$1/', $result);
        
        return $result;
    }
}