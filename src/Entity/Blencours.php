<?php

namespace App\Entity;

use App\Repository\BlencoursRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlencoursRepository::class)]
class Blencours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $numBl = null;

    #[ORM\Column(length: 255)]
    private ?string $statut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $adddate = null;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private ?bool $Pickingok = false;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private ?bool $Pickingnok = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumBl(): ?string
    {
        return $this->numBl;
    }

    public function setNumBl(string $numBl): static
    {
        $this->numBl = $numBl;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getAdddate(): ?\DateTimeImmutable
    {
        return $this->adddate;
    }

    public function setAdddate(?\DateTimeImmutable $adddate): static
    {
        $this->adddate = $adddate;

        return $this;
    }

    public function isPickingok(): ?bool
    {
        return $this->Pickingok;
    }

    public function getPickingok(): ?bool
    {
        return $this->Pickingok;
    }

    public function setPickingok(bool $Pickingok): static
    {
        $this->Pickingok = $Pickingok;

        return $this;
    }

    public function isPickingnok(): ?bool
    {
        return $this->Pickingnok;
    }

    public function getPickingnok(): ?bool
    {
        return $this->Pickingnok;
    }

    public function setPickingnok(bool $Pickingnok): static
    {
        $this->Pickingnok = $Pickingnok;

        return $this;
    }
}