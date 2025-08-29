<?php

namespace App\Entity;

use App\Repository\ProblemeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProblemeRepository::class)]
class Probleme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $emplacement = null;

    #[ORM\Column(length: 255)]
    private ?string $nopal = null;

    #[ORM\Column(length: 255)]
    private ?string $codeprod = null;

    #[ORM\Column(length: 255)]
    private ?string $dsignprod = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateSignalement = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateResolution = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $signaledBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resolvedBy = null;

    #[ORM\Column]
    private ?int $uvtotal = null;

    #[ORM\Column]
    private ?int $ucdispo = null;

    #[ORM\Column]
    private ?int $urdispo = null;

    #[ORM\Column]
    private ?int $uvensortie = null;

    #[ORM\Column]
    private ?int $urbloquee = null;

    #[ORM\Column(length: 255)]
    private ?string $zone = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $infosPalette = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function setNopal(string $nopal): static
    {
        $this->nopal = $nopal;
        return $this;
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

    public function setDsignprod(string $dsignprod): static
    {
        $this->dsignprod = $dsignprod;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateSignalement(): ?\DateTimeInterface
    {
        return $this->dateSignalement;
    }

    public function setDateSignalement(\DateTimeInterface $dateSignalement): static
    {
        $this->dateSignalement = $dateSignalement;
        return $this;
    }

    public function getDateResolution(): ?\DateTimeInterface
    {
        return $this->dateResolution;
    }

    public function setDateResolution(?\DateTimeInterface $dateResolution): static
    {
        $this->dateResolution = $dateResolution;
        return $this;
    }

    public function getSignaledBy(): ?string
    {
        return $this->signaledBy;
    }

    public function setSignaledBy(?string $signaledBy): static
    {
        $this->signaledBy = $signaledBy;
        return $this;
    }

    public function getResolvedBy(): ?string
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?string $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;
        return $this;
    }

    public function getUvtotal(): ?int
    {
        return $this->uvtotal;
    }

    public function setUvtotal(int $uvtotal): static
    {
        $this->uvtotal = $uvtotal;
        return $this;
    }

    public function getUcdispo(): ?int
    {
        return $this->ucdispo;
    }

    public function setUcdispo(int $ucdispo): static
    {
        $this->ucdispo = $ucdispo;
        return $this;
    }

    public function getUrdispo(): ?int
    {
        return $this->urdispo;
    }

    public function setUrdispo(int $urdispo): static
    {
        $this->urdispo = $urdispo;
        return $this;
    }

    public function getUvensortie(): ?int
    {
        return $this->uvensortie;
    }

    public function setUvensortie(int $uvensortie): static
    {
        $this->uvensortie = $uvensortie;
        return $this;
    }

    public function getUrbloquee(): ?int
    {
        return $this->urbloquee;
    }

    public function setUrbloquee(int $urbloquee): static
    {
        $this->urbloquee = $urbloquee;
        return $this;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function setZone(string $zone): static
    {
        $this->zone = $zone;
        return $this;
    }

    public function getInfosPalette(): ?array
    {
        return $this->infosPalette;
    }

    public function setInfosPalette(?array $infosPalette): static
    {
        $this->infosPalette = $infosPalette;
        return $this;
    }
}