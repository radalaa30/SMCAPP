<?php

namespace App\Service;

use Symfony\Component\Panther\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class ExcelDownloaderService
{
    private $params;
    private $logger;
    private $downloadDir;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->logger = $logger;
        $this->downloadDir = sys_get_temp_dir() . '/excel_downloads';

        if (!file_exists($this->downloadDir)) {
            mkdir($this->downloadDir, 0777, true);
        }
    }

    public function downloadExcelFile(callable $progressCallback = null): string
    {
        $this->logger->info('Début du téléchargement du fichier Excel');
        if ($progressCallback) {
            $progressCallback('Connexion au site...', 10);
        }

        $chromeOptions = [
            'args' => ['--no-sandbox', '--disable-dev-shm-usage'],
            'prefs' => [
                'download.default_directory' => $this->downloadDir,
                'download.prompt_for_download' => false,
                'download.directory_upgrade' => true,
                'safebrowsing.enabled' => false,
            ]
        ];

        $client = Client::createChromeClient(null, null, $chromeOptions);

        try {
            $client->request('GET', $this->params->get('site_login_url'));

            if ($client->getCrawler()->filter('input[name="username"], input[name="password"]')->count() > 0) {
                $client->submitForm('Connexion', [
                    'username' => $this->params->get('site_username'),
                    'password' => $this->params->get('site_password'),
                ]);

                $client->waitForVisibility('.Menu_Menu1_1', 20);
            }

            $client->waitForVisibility('#Menu_Menu1', 20);

            $client->executeScript("if (typeof __doPostBack === 'function') {
                __doPostBack('Menu\$Menu1','Editions\\\\Editions');
            }");

            $client->waitForVisibility('#ArbreRapport_TreeView1', 20);

            $client->executeScript("
                const nodes = document.querySelectorAll('a[id*=\"ArbreRapport_TreeView\"]');
                for (let node of nodes) {
                    if (node.innerText.includes('CDSUD_SUIVI_PREPARATION_DU_JOUR')) {
                        node.click();
                        break;
                    }
                }
            ");

            $client->waitForVisibility('#Imprimer', 20);

            $client->executeScript("
                const printBtn = document.getElementById('Imprimer');
                if (printBtn) printBtn.click();
            ");

            $this->waitForDownload($client, 60, $progressCallback);

            $filePath = $this->getLatestDownloadedFile();

            $client->quit();

            return $filePath;
        } catch (\Exception $e) {
            $client->takeScreenshot($this->downloadDir . '/error_' . time() . '.png');
            $client->quit();
            $this->logger->error('Erreur : ' . $e->getMessage());
            if ($progressCallback) {
                $progressCallback('Erreur : ' . $e->getMessage(), -1);
            }
            throw $e;
        }
    }

    private function waitForDownload(Client $client, int $timeout, callable $progressCallback = null): void
    {
        $start = time();
        $initialFiles = $this->getDownloadedFiles();

        while (time() - $start < $timeout) {
            sleep(1);
            $newFiles = $this->getDownloadedFiles();

            $diff = array_diff($newFiles, $initialFiles);
            $finished = array_filter($diff, fn($f) => !str_ends_with($f, '.crdownload') && !str_ends_with($f, '.part'));

            if (count($finished) > 0) {
                return;
            }
        }

        throw new \Exception("Téléchargement non détecté dans le délai imparti.");
    }

    private function getDownloadedFiles(): array
    {
        return array_diff(scandir($this->downloadDir), ['.', '..']);
    }

    private function getLatestDownloadedFile(): string
    {
        $files = $this->getDownloadedFiles();

        $latestFile = '';
        $latestTime = 0;

        foreach ($files as $file) {
            $path = $this->downloadDir . '/' . $file;
            if (filemtime($path) > $latestTime) {
                $latestTime = filemtime($path);
                $latestFile = $path;
            }
        }

        if (!$latestFile) {
            throw new \Exception("Aucun fichier téléchargé trouvé.");
        }

        return $latestFile;
    }
}
