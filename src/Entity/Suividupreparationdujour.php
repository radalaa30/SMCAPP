<?php

namespace App\Entity;

use App\Repository\SuividupreparationdujourRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuividupreparationdujourRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Suividupreparationdujour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $CodeProduit = null;

    #[ORM\Column(length: 100, nullable: true )]
    public ?string $Gencode_uv = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $No_Palette = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $No_Pal = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Flasher = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Zone = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Adresse = null;

    #[ORM\Column(nullable: true)]
    public ?int $Nb_Pal = null;

    #[ORM\Column(nullable: true)]
    public ?int $Nb_col = null;

    #[ORM\Column(nullable: true)]
    public ?int $Nb_art = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Nb_regr = null;
   
    #[ORM\Column(length: 100, nullable: true)]
    public ?string $No_Bl = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $Date_liv = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $No_Cmd = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Client = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Statut_Cde = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Code_Client = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Preparateur = null;

    #[ORM\Column(length: 100, nullable: true)]
    public ?string $Transporteur = null;

    #[ORM\Column(name:'updatedAt',type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $valider = null;

    // Getters et Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeProduit(): ?string
    {
        return $this->CodeProduit;
    }

    public function setCodeProduit(string $CodeProduit): static
    {
        $this->CodeProduit = $CodeProduit;
        return $this;
    }

    public function getGencodeUv(): ?string
    {
        return $this->Gencode_uv;
    }

    public function setGencodeUv(string $Gencode_uv): static
    {
        $this->Gencode_uv = $Gencode_uv;
        return $this;
    }

    public function getNoPalette(): ?string
    {
        return $this->No_Palette;
    }

    public function setNoPalette(?string $No_Palette): static
    {
        $this->No_Palette = $No_Palette;
        return $this;
    }

    public function getNoPal(): ?string
    {
        return $this->No_Pal;
    }

    public function setNoPal(?string $No_Pal): static
    {
        $this->No_Pal = $No_Pal;
        return $this;
    }

    public function getFlasher(): ?string
    {
        return $this->Flasher;
    }

    public function setFlasher(?string $Flasher): static
    {
        $this->Flasher = $Flasher;
        return $this;
    }

    public function getZone(): ?string
    {
        return $this->Zone;
    }

    public function setZone(?string $Zone): static
    {
        $this->Zone = $Zone;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->Adresse;
    }

    public function setAdresse(?string $Adresse): static
    {
        $this->Adresse = $Adresse;
        return $this;
    }

    public function getNbPal(): ?int
    {
        return $this->Nb_Pal;
    }

    public function setNbPal(?int $Nb_Pal): static
    {
        $this->Nb_Pal = $Nb_Pal;
        return $this;
    }

    public function getNbCol(): ?int
    {
        return $this->Nb_col;
    }

    public function setNbCol(?int $Nb_col): static
    {
        $this->Nb_col = $Nb_col;
        return $this;
    }

    public function getNbArt(): ?int
    {
        return $this->Nb_art;
    }

    public function setNbArt(?int $Nb_art): static
    {
        $this->Nb_art = $Nb_art;
        return $this;
    }

    public function getNbRegr(): ?string
    {
        return $this->Nb_regr;
    }

    public function setNbRegr(?string $Nb_regr): static
    {
        $this->Nb_regr = $Nb_regr;
        return $this;
    }

    public function getNoBl(): ?string
    {
        return $this->No_Bl;
    }

    public function setNoBl(?string $No_Bl): static
    {
        $this->No_Bl = $No_Bl;
        return $this;
    }

    public function getDateLiv(): ?\DateTimeImmutable
    {
        return $this->Date_liv;
    }

    public function setDateLiv(\DateTimeImmutable $Date_liv): static
    {
        $this->Date_liv = $Date_liv;
        return $this;
    }

    public function getNoCmd(): ?string
    {
        return $this->No_Cmd;
    }

    public function setNoCmd(?string $No_Cmd): static
    {
        $this->No_Cmd = $No_Cmd;
        return $this;
    }

    public function getClient(): ?string
    {
        return $this->Client;
    }

    public function setClient(?string $Client): static
    {
        $this->Client = $Client;
        return $this;
    }

    public function getStatutCde(): ?string
    {
        return $this->Statut_Cde;
    }

    public function setStatutCde(?string $Statut_Cde): static
    {
        $this->Statut_Cde = $Statut_Cde;
        return $this;
    }

    public function getCodeClient(): ?string
    {
        return $this->Code_Client;
    }

    public function setCodeClient(?string $Code_Client): static
    {
        $this->Code_Client = $Code_Client;
        return $this;
    }

    public function getPreparateur(): ?string
    {
        return $this->Preparateur;
    }

    public function setPreparateur(?string $Preparateur): static
    {
        $this->Preparateur = $Preparateur;
        return $this;
    }

    public function getTransporteur(): ?string
    {
        return $this->Transporteur;
    }

    public function setTransporteur(?string $Transporteur): static
    {
        $this->Transporteur = $Transporteur;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getValider(): ?\DateTimeInterface
    {
        return $this->valider;
    }

    public function setValider(?\DateTimeInterface $valider): static
    {
        $this->valider = $valider;
        return $this;
    }

    /**
     * Méthode pour la mise à jour automatique de la date de mise à jour
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}