<?php

namespace App\Entity;

use App\Repository\ArticlesstockRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticlesstockRepository::class)]
class Articlesstock
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private ?string $reference = null;

    #[ORM\Column(length: 255)]
    private ?string $designation = null;

    #[ORM\Column]
    private ?int $uvEnStock = null;

    #[ORM\Column]
    private ?int $nbrucPal = null;

    #[ORM\Column(length: 50)]
    private ?string $picking = null;

    #[ORM\Column(length: 100)]
    private ?string $desUc = null;

    #[ORM\Column(length: 50)]
    private ?string $pcb = null;

    #[ORM\Column(length: 100)]
    private ?string $desUv = null;

    #[ORM\Column(length: 50)]
    private ?string $serie = null;

    #[ORM\Column(length: 50)]
    private ?string $serieQuestion = null;

    #[ORM\Column(length: 50)]
    private ?string $sku = null;

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(string $designation): static
    {
        $this->designation = $designation;
        return $this;
    }

    public function getUvEnStock(): ?int
    {
        return $this->uvEnStock;
    }

    public function setUvEnStock(int $uvEnStock): static
    {
        $this->uvEnStock = $uvEnStock;
        return $this;
    }

    public function getNbrucPal(): ?int
    {
        return $this->nbrucPal;
    }

    public function setNbrucPal(int $nbrucPal): static
    {
        $this->nbrucPal = $nbrucPal;
        return $this;
    }

    public function getPicking(): ?string
    {
        return $this->picking;
    }

    public function setPicking(string $picking): static
    {
        $this->picking = $picking;
        return $this;
    }

    public function getDesUc(): ?string
    {
        return $this->desUc;
    }

    public function setDesUc(string $desUc): static
    {
        $this->desUc = $desUc;
        return $this;
    }

    public function getPcb(): ?string
    {
        return $this->pcb;
    }

    public function setPcb(string $pcb): static
    {
        $this->pcb = $pcb;
        return $this;
    }

    public function getDesUv(): ?string
    {
        return $this->desUv;
    }

    public function setDesUv(string $desUv): static
    {
        $this->desUv = $desUv;
        return $this;
    }

    public function getSerie(): ?string
    {
        return $this->serie;
    }

    public function setSerie(string $serie): static
    {
        $this->serie = $serie;
        return $this;
    }

    public function getSerieQuestion(): ?string
    {
        return $this->serieQuestion;
    }

    public function setSerieQuestion(string $serieQuestion): static
    {
        $this->serieQuestion = $serieQuestion;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = $sku;
        return $this;
    }
}