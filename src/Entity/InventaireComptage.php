<?php

namespace App\Entity;

use App\Repository\InventaireComptageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventaireComptageRepository::class)]
#[ORM\Table(name: 'inventaire_comptage')]
#[ORM\Index(columns: ['codeprod'], name: 'idx_comptage_codeprod')]
#[ORM\Index(columns: ['session_inventaire'], name: 'idx_comptage_session')]
#[ORM\Index(columns: ['date_comptage'], name: 'idx_comptage_date')]
#[ORM\Index(columns: ['emplacement'], name: 'idx_comptage_emplacement')]
class InventaireComptage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $codeprod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dsignprod = null;

    #[ORM\Column(length: 255)]
    private ?string $emplacement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nopal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $zone = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $qte_theorique = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $qte_comptee = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $ecart = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $type_ecart = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $valide = false;

    #[ORM\Column(length: 100)]
    private ?string $operateur = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $date_comptage = null;

    #[ORM\Column(length: 100)]
    private ?string $session_inventaire = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $date_validation = null;

    public function __construct()
    {
        $this->date_comptage = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeprod(): ?string
    {
        return $this->codeprod;
    }

    public function setCodeprod(string $codeprod): static
    {
        $this->codeprod = $codeprod;
        return $this;
    }

    public function getDsignprod(): ?string
    {
        return $this->dsignprod;
    }

    public function setDsignprod(?string $dsignprod): static
    {
        $this->dsignprod = $dsignprod;
        return $this;
    }

    public function getEmplacement(): ?string
    {
        return $this->emplacement;
    }

    public function setEmplacement(string $emplacement): static
    {
        $this->emplacement = $emplacement;
        return $this;
    }

    public function getNopal(): ?string
    {
        return $this->nopal;
    }

    public function setNopal(?string $nopal): static
    {
        $this->nopal = $nopal;
        return $this;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function setZone(?string $zone): static
    {
        $this->zone = $zone;
        return $this;
    }

    public function getQteTheorique(): ?int
    {
        return $this->qte_theorique;
    }

    public function setQteTheorique(int $qte_theorique): static
    {
        $this->qte_theorique = $qte_theorique;
        $this->calculerEcart();
        return $this;
    }

    public function getQteComptee(): ?int
    {
        return $this->qte_comptee;
    }

    public function setQteComptee(int $qte_comptee): static
    {
        $this->qte_comptee = $qte_comptee;
        $this->calculerEcart();
        return $this;
    }

    public function getEcart(): ?int
    {
        return $this->ecart;
    }

    public function setEcart(?int $ecart): static
    {
        $this->ecart = $ecart;
        return $this;
    }

    private function calculerEcart(): void
    {
        if ($this->qte_comptee !== null && $this->qte_theorique !== null) {
            $this->ecart = $this->qte_comptee - $this->qte_theorique;
        }
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getTypeEcart(): ?string
    {
        return $this->type_ecart;
    }

    public function setTypeEcart(?string $type_ecart): static
    {
        $this->type_ecart = $type_ecart;
        return $this;
    }

    public function isValide(): bool
    {
        return $this->valide;
    }

    public function setValide(bool $valide): static
    {
        $this->valide = $valide;
        if ($valide && $this->date_validation === null) {
            $this->date_validation = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getOperateur(): ?string
    {
        return $this->operateur;
    }

    public function setOperateur(string $operateur): static
    {
        $this->operateur = $operateur;
        return $this;
    }

    public function getDateComptage(): ?\DateTimeImmutable
    {
        return $this->date_comptage;
    }

    public function setDateComptage(\DateTimeImmutable $date_comptage): static
    {
        $this->date_comptage = $date_comptage;
        return $this;
    }

    public function getSessionInventaire(): ?string
    {
        return $this->session_inventaire;
    }

    public function setSessionInventaire(string $session_inventaire): static
    {
        $this->session_inventaire = $session_inventaire;
        return $this;
    }

    public function getDateValidation(): ?\DateTimeImmutable
    {
        return $this->date_validation;
    }

    public function setDateValidation(?\DateTimeImmutable $date_validation): static
    {
        $this->date_validation = $date_validation;
        return $this;
    }

    // ========================================
    // MÉTHODES UTILITAIRES
    // ========================================

    /**
     * Vérifie si le comptage a un écart
     */
    public function aUnEcart(): bool
    {
        return $this->ecart !== null && $this->ecart !== 0;
    }

    /**
     * Calcule le pourcentage d'écart par rapport à la quantité théorique
     */
    public function getPourcentageEcart(): ?float
    {
        if ($this->qte_theorique === null || $this->qte_theorique === 0 || $this->ecart === null) {
            return null;
        }
        return round(($this->ecart / $this->qte_theorique) * 100, 2);
    }

    /**
     * Vérifie si l'écart est considéré comme majeur
     */
    public function isEcartMajeur(float $seuilPourcentage = 10.0): bool
    {
        $pourcentage = $this->getPourcentageEcart();
        return $pourcentage !== null && abs($pourcentage) > $seuilPourcentage;
    }

    /**
     * Retourne le type d'écart automatique basé sur la valeur
     */
    public function getTypeEcartAuto(): string
    {
        if ($this->ecart === null) {
            return 'non_defini';
        }
        
        if ($this->ecart > 0) {
            return 'surplus';
        } elseif ($this->ecart < 0) {
            return 'manquant';
        } else {
            return 'conforme';
        }
    }

    /**
     * Retourne la classe CSS appropriée pour l'affichage de l'écart
     */
    public function getEcartCssClass(): string
    {
        if ($this->ecart === null || $this->ecart === 0) {
            return 'text-muted';
        }
        
        return $this->ecart > 0 ? 'text-success' : 'text-danger';
    }

    /**
     * Retourne l'icône appropriée pour l'affichage de l'écart
     */
    public function getEcartIcon(): string
    {
        if ($this->ecart === null || $this->ecart === 0) {
            return 'fas fa-equals';
        }
        
        return $this->ecart > 0 ? 'fas fa-plus' : 'fas fa-minus';
    }

    /**
     * Vérifie si le comptage nécessite une attention particulière
     */
    public function necessiteAttention(): bool
    {
        return $this->aUnEcart() || 
               $this->isEcartMajeur() || 
               !empty($this->commentaire) || 
               !$this->valide;
    }

    /**
     * Retourne le statut formaté du comptage
     */
    public function getStatutFormate(): array
    {
        if (!$this->valide) {
            return [
                'text' => 'En attente',
                'class' => 'badge bg-secondary',
                'icon' => 'fas fa-clock'
            ];
        }
        
        if ($this->aUnEcart()) {
            if ($this->isEcartMajeur()) {
                return [
                    'text' => 'Écart majeur',
                    'class' => 'badge bg-danger',
                    'icon' => 'fas fa-exclamation-triangle'
                ];
            } else {
                return [
                    'text' => 'Écart mineur',
                    'class' => 'badge bg-warning',
                    'icon' => 'fas fa-exclamation-circle'
                ];
            }
        }
        
        return [
            'text' => 'Validé - Conforme',
            'class' => 'badge bg-success',
            'icon' => 'fas fa-check-circle'
        ];
    }

    /**
     * Retourne un résumé textuel du comptage
     */
    public function getResume(): string
    {
        $resume = sprintf(
            '%s - %s: %d→%d',
            $this->codeprod,
            $this->emplacement,
            $this->qte_theorique ?? 0,
            $this->qte_comptee ?? 0
        );
        
        if ($this->aUnEcart()) {
            $resume .= sprintf(' (écart: %+d)', $this->ecart);
        }
        
        return $resume;
    }

    /**
     * Représentation string de l'entité
     */
    public function __toString(): string
    {
        return sprintf('%s - %s (%s)', 
            $this->codeprod, 
            $this->emplacement, 
            $this->ecart >= 0 ? '+' . $this->ecart : $this->ecart
        );
    }
}