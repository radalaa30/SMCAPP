<?php

namespace App\Entity;

use App\Repository\ListeProduitsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ListeProduitsRepository::class)]
class ListeProduits
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $ref = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $des = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pinkg = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $uvEnStock = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $seuilreapp = null;

    // --------------------
    // Getters / Setters
    // --------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;
        return $this;
    }

    public function getDes(): ?string
    {
        return $this->des;
    }

    public function setDes(?string $des): self
    {
        $this->des = $des;
        return $this;
    }

    public function getPinkg(): ?string
    {
        return $this->pinkg;
    }

    public function setPinkg(?string $pinkg): self
    {
        $this->pinkg = $pinkg;
        return $this;
    }

    public function getUvEnStock(): ?string
    {
        return $this->uvEnStock;
    }

    public function setUvEnStock(?string $uvEnStock): self
    {
        $this->uvEnStock = $uvEnStock;
        return $this;
    }

    public function getSeuilreapp(): ?string
    {
        return $this->seuilreapp;
    }

    public function setSeuilreapp(string $seuilreapp): self
    {
        $this->seuilreapp = $seuilreapp;
        return $this;
    }
}
