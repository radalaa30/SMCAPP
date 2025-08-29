<?php

namespace App\Entity;

use App\Repository\ArticlesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticlesRepository::class)]
class Articles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Code_produit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Gencode_uv = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Num_palette = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Numero_palette = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Flasher = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Zone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Nb_pal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Nb_col = null;

    #[ORM\Column(length: 255)]
    private ?string $Nb_art = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Pal_regr = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numBl = null;

    #[ORM\Column(length: 255)]
    private ?string $date = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeProduit(): ?string
    {
        return $this->Code_produit;
    }

    public function setCodeProduit(string $Code_produit): static
    {
        $this->Code_produit = $Code_produit;

        return $this;
    }

    public function getGencodeUv(): ?string
    {
        return $this->Gencode_uv;
    }

    public function setGencodeUv(?string $Gencode_uv): static
    {
        $this->Gencode_uv = $Gencode_uv;

        return $this;
    }

    public function getNumPalette(): ?string
    {
        return $this->Num_palette;
    }

    public function setNumPalette(?string $Num_palette): static
    {
        $this->Num_palette = $Num_palette;

        return $this;
    }

    public function getNumeroPalette(): ?string
    {
        return $this->Numero_palette;
    }

    public function setNumeroPalette(?string $Numero_palette): static
    {
        $this->Numero_palette = $Numero_palette;

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

    public function getNbPal(): ?string
    {
        return $this->Nb_pal;
    }

    public function setNbPal(?string $Nb_pal): static
    {
        $this->Nb_pal = $Nb_pal;

        return $this;
    }

    public function getNbCol(): ?string
    {
        return $this->Nb_col;
    }

    public function setNbCol(?string $Nb_col): static
    {
        $this->Nb_col = $Nb_col;

        return $this;
    }

    public function getNbArt(): ?string
    {
        return $this->Nb_art;
    }

    public function setNbArt(string $Nb_art): static
    {
        $this->Nb_art = $Nb_art;

        return $this;
    }

    public function getPalRegr(): ?string
    {
        return $this->Pal_regr;
    }

    public function setPalRegr(?string $Pal_regr): static
    {
        $this->Pal_regr = $Pal_regr;

        return $this;
    }

    public function getNumBl(): ?string
    {
        return $this->numBl;
    }

    public function setNumBl(?string $numBl): static
    {
        $this->numBl = $numBl;

        return $this;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }

    public function setDate(string $date): static
    {
        $this->date = $date;

        return $this;
    }
}
