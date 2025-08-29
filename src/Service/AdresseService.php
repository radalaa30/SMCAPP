<?php
namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service pour la gestion et la normalisation des adresses
 */
class AdresseService
{
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Normalise une adresse en remplaçant les préfixes '10' par 'C'
     * et en appliquant d'autres règles de normalisation si nécessaire
     *
     * @param string|null $adresse L'adresse à normaliser
     * @return string L'adresse normalisée
     */
    public function normalizeAdresse(?string $adresse): string
    {
        if ($adresse === null || trim($adresse) === '') {
            return '';
        }
        
        $adresseNormalisee = trim($adresse);
        
        // Remplace le préfixe '10' par 'C'
        $adresseNormalisee = preg_replace('/^10/', 'C', $adresseNormalisee);
        
        // Log pour des raisons de débogage si nécessaire
        if ($adresseNormalisee !== $adresse) {
            $this->logger->debug('Adresse normalisée', [
                'avant' => $adresse,
                'apres' => $adresseNormalisee
            ]);
        }
        
        return $adresseNormalisee;
    }
    
    /**
     * Vérifie si une adresse est valide selon les règles métier
     *
     * @param string $adresse L'adresse à vérifier
     * @return bool True si l'adresse est valide, false sinon
     */
    public function isValidAdresse(string $adresse): bool
    {
        // Implémentation des règles de validation des adresses
        // Par exemple, vérifier si l'adresse suit un format particulier
        
        // Vérification que l'adresse n'est pas vide
        if (empty(trim($adresse))) {
            return false;
        }
        
        // Vérification du format (exemple)
        // Format attendu: Zone:Adresse (ex: C2S:A-01)
        if (!preg_match('/^[A-Z0-9]+:[A-Z0-9\-]+$/', $adresse)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Vérifie si l'adresse correspond aux critères d'une adresse Cold
     *
     * @param string $adresse L'adresse à vérifier
     * @return bool True si c'est une adresse Cold, false sinon
     */
    public function isColdAdresse(string $adresse): bool
    {
        $parts = explode(':', $adresse);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        $zone = $parts[0];
        $emplacement = $parts[1];
        
        // Par exemple, si la zone est "C2S" et l'emplacement ne commence pas par "G"
        if ($zone === 'C2S' && !str_starts_with($emplacement, 'G')) {
            return true;
        }
        
        // Autres règles métier spécifiques pour identifier les adresses Cold
        if (str_ends_with($emplacement, '-01') || 
            str_ends_with($emplacement, '-02') || 
            str_ends_with($emplacement, '-03') || 
            str_ends_with($emplacement, '-04')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Extrait les composants (zone, emplacement) d'une adresse
     *
     * @param string $adresse L'adresse à décomposer
     * @return array Tableau associatif avec les composants de l'adresse
     */
    public function extractAdresseComponents(string $adresse): array
    {
        $components = ['zone' => '', 'emplacement' => ''];
        
        $parts = explode(':', $adresse);
        
        if (count($parts) === 2) {
            $components['zone'] = $parts[0];
            $components['emplacement'] = $parts[1];
        }
        
        return $components;
    }
}