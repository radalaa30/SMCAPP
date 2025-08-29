<?php

namespace App\Entity;

use App\Repository\SuividupreparationdujourReaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuividupreparationdujourReaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SuividupreparationdujourRea
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'code_produit', length: 100, nullable: true)]
    private ?string $codeProduit = null;

    #[ORM\Column(name: 'gencode_uv', length: 100, nullable: true)]
    private ?string $gencodeUv = null;

    #[ORM\Column(name: 'no_palette', length: 100, nullable: true)]
    private ?string $noPalette = null;

    #[ORM\Column(name: 'no_pal', length: 100, nullable: true)]
    private ?string $noPal = null;

    #[ORM\Column(name: 'flasher', length: 100, nullable: true)]
    private ?string $flasher = null;

    #[ORM\Column(name: 'zone', length: 100, nullable: true)]
    private ?string $zone = null;

    #[ORM\Column(name: 'adresse', length: 100, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(name: 'nb_pal', nullable: true)]
    private ?int $nbPal = null;

    #[ORM\Column(name: 'nb_col', nullable: true)]
    private ?int $nbCol = null;

    #[ORM\Column(name: 'nb_art', nullable: true)]
    private ?int $nbArt = null;

    #[ORM\Column(name: 'nb_regr', length: 100, nullable: true)]
    private ?string $nbRegr = null;

    #[ORM\Column(name: 'no_bl', length: 100, nullable: true)]
    private ?string $noBl = null;

    #[ORM\Column(name: 'date_liv', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateLiv = null;

    #[ORM\Column(name: 'no_cmd', length: 100, nullable: true)]
    private ?string $noCmd = null;

    #[ORM\Column(name: 'client', length: 100, nullable: true)]
    private ?string $client = null;

    #[ORM\Column(name: 'statut_cde', length: 100, nullable: true)]
    private ?string $statutCde = null;

    #[ORM\Column(name: 'code_client', length: 100, nullable: true)]
    private ?string $codeClient = null;

    #[ORM\Column(name: 'preparateur', length: 100, nullable: true)]
    private ?string $preparateur = null;

    #[ORM\Column(name: 'transporteur', length: 100, nullable: true)]
    private ?string $transporteur = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'valider', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $valider = null;

    // Getters / Setters

    public function getId(): ?int { return $this->id; }

    public function getCodeProduit(): ?string { return $this->codeProduit; }
    public function setCodeProduit(?string $v): self { $this->codeProduit = $v; return $this; }

    public function getGencodeUv(): ?string { return $this->gencodeUv; }
    public function setGencodeUv(?string $v): self { $this->gencodeUv = $v; return $this; }

    public function getNoPalette(): ?string { return $this->noPalette; }
    public function setNoPalette(?string $v): self { $this->noPalette = $v; return $this; }

    public function getNoPal(): ?string { return $this->noPal; }
    public function setNoPal(?string $v): self { $this->noPal = $v; return $this; }

    public function getFlasher(): ?string { return $this->flasher; }
    public function setFlasher(?string $v): self { $this->flasher = $v; return $this; }

    public function getZone(): ?string { return $this->zone; }
    public function setZone(?string $v): self { $this->zone = $v; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $v): self { $this->adresse = $v; return $this; }

    public function getNbPal(): ?int { return $this->nbPal; }
    public function setNbPal(?int $v): self { $this->nbPal = $v; return $this; }

    public function getNbCol(): ?int { return $this->nbCol; }
    public function setNbCol(?int $v): self { $this->nbCol = $v; return $this; }

    public function getNbArt(): ?int { return $this->nbArt; }
    public function setNbArt(?int $v): self { $this->nbArt = $v; return $this; }

    public function getNbRegr(): ?string { return $this->nbRegr; }
    public function setNbRegr(?string $v): self { $this->nbRegr = $v; return $this; }

    public function getNoBl(): ?string { return $this->noBl; }
    public function setNoBl(?string $v): self { $this->noBl = $v; return $this; }

    public function getDateLiv(): ?\DateTimeImmutable { return $this->dateLiv; }
    public function setDateLiv(?\DateTimeImmutable $v): self { $this->dateLiv = $v; return $this; }

    public function getNoCmd(): ?string { return $this->noCmd; }
    public function setNoCmd(?string $v): self { $this->noCmd = $v; return $this; }

    public function getClient(): ?string { return $this->client; }
    public function setClient(?string $v): self { $this->client = $v; return $this; }

    public function getStatutCde(): ?string { return $this->statutCde; }
    public function setStatutCde(?string $v): self { $this->statutCde = $v; return $this; }

    public function getCodeClient(): ?string { return $this->codeClient; }
    public function setCodeClient(?string $v): self { $this->codeClient = $v; return $this; }

    public function getPreparateur(): ?string { return $this->preparateur; }
    public function setPreparateur(?string $v): self { $this->preparateur = $v; return $this; }

    public function getTransporteur(): ?string { return $this->transporteur; }
    public function setTransporteur(?string $v): self { $this->transporteur = $v; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function getValider(): ?\DateTimeInterface { return $this->valider; }
    public function setValider(?\DateTimeInterface $v): self { $this->valider = $v; return $this; }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
