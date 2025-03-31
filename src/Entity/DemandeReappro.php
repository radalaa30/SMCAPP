<?php

namespace App\Entity;

use App\Repository\DemandeReapproRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeReapproRepository::class)]
class DemandeReappro
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $idReappro = null;

    #[ORM\Column]
    private ?int $idPreparateur = null;

    #[ORM\Column(nullable: true)]
    private ?int $idCariste = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $SonPicking = null;

    #[ORM\Column(length: 255)]
    private ?string $Adresse = null;

    #[ORM\Column(length: 255)]
    private ?string $Statut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $CreateAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $UpdateAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $UsernamePrep = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $UsernameCariste = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdReappro(): ?int
    {
        return $this->idReappro;
    }

    public function setIdReappro(int $idReappro): static
    {
        $this->idReappro = $idReappro;

        return $this;
    }

    public function getIdPreparateur(): ?int
    {
        return $this->idPreparateur;
    }

    public function setIdPreparateur(int $idPreparateur): static
    {
        $this->idPreparateur = $idPreparateur;

        return $this;
    }

    public function getIdCariste(): ?int
    {
        return $this->idCariste;
    }

    public function setIdCariste(?int $idCariste): static
    {
        $this->idCariste = $idCariste;

        return $this;
    }

    public function getSonPicking(): ?string
    {
        return $this->SonPicking;
    }

    public function setSonPicking(?string $SonPicking): static
    {
        $this->SonPicking = $SonPicking;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->Adresse;
    }

    public function setAdresse(string $Adresse): static
    {
        $this->Adresse = $Adresse;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->Statut;
    }

    public function setStatut(string $Statut): static
    {
        $this->Statut = $Statut;

        return $this;
    }

    public function getCreateAt(): ?\DateTimeImmutable
    {
        return $this->CreateAt;
    }

    public function setCreateAt(\DateTimeImmutable $CreateAt): static
    {
        $this->CreateAt = $CreateAt;

        return $this;
    }

    public function getUpdateAt(): ?\DateTimeImmutable
    {
        return $this->UpdateAt;
    }

    public function setUpdateAt(?\DateTimeImmutable $UpdateAt): static
    {
        $this->UpdateAt = $UpdateAt;

        return $this;
    }

    public function getUsernamePrep(): ?string
    {
        return $this->UsernamePrep;
    }

    public function setUsernamePrep(?string $UsernamePrep): static
    {
        $this->UsernamePrep = $UsernamePrep;

        return $this;
    }

    public function getUsernameCariste(): ?string
    {
        return $this->UsernameCariste;
    }

    public function setUsernameCariste(?string $UsernameCariste): static
    {
        $this->UsernameCariste = $UsernameCariste;

        return $this;
    }
}
