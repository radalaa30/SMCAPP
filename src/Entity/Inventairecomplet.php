<?php

namespace App\Entity;

use App\Repository\InventairecompletRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventairecompletRepository::class)]
class Inventairecomplet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nopalinfo = null;

    #[ORM\Column(length: 255)]
    private ?string $codeprod = null;

    #[ORM\Column(length: 255)]
    private ?string $dsignprod = null;

    #[ORM\Column(length: 255)]
    private ?string $emplacement = null;

    #[ORM\Column(length: 255)]
    private ?string $nopal = null;

    #[ORM\Column]
    private ?int $urdispo = null;

    #[ORM\Column]
    private ?int $ucdispo = null;

    #[ORM\Column(length: 255)]
    private ?string $uvtotal = null;

    #[ORM\Column]
    private ?int $uvensortie = null;

    #[ORM\Column]
    private ?int $urbloquee = null;

    #[ORM\Column(length: 255)]
    private ?string $zone = null;

    #[ORM\Column(length: 255)]
    private ?string $emplacementdoublon = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateentree = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNopalinfo(): ?string
    {
        return $this->nopalinfo;
    }

    public function setNopalinfo(string $nopalinfo): static
    {
        $this->nopalinfo = $nopalinfo;

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

    public function getUrdispo(): ?int
    {
        return $this->urdispo;
    }

    public function setUrdispo(int $urdispo): static
    {
        $this->urdispo = $urdispo;

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

    public function getUvtotal(): ?int
    {
        return $this->uvtotal;
    }

    public function setUvtotal(int $uvtotal): static
    {
        $this->uvtotal = $uvtotal;

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

    public function getEmplacementdoublon(): ?string
    {
        return $this->emplacementdoublon;
    }

    public function setEmplacementdoublon(string $emplacementdoublon): static
    {
        $this->emplacementdoublon = $emplacementdoublon;

        return $this;
    }

    public function getDateentree(): ?\DateTimeImmutable
    {
        return $this->dateentree;
    }

    public function setDateentree(\DateTimeImmutable $dateentree): static
    {
        $this->dateentree = $dateentree;

        return $this;
    }
}
