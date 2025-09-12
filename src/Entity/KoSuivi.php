<?php

namespace App\Entity;

use App\Repository\KoSuiviRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KoSuiviRepository::class)]
#[ORM\HasLifecycleCallbacks]
class KoSuivi
{
    public const STATUTS = ['NOUVEAU', 'EN_COURS', 'TRAITE', 'REJETE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Suividupreparationdujour::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Suividupreparationdujour $suivi = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'NOUVEAU';

    #[ORM\Column(options: ['default' => false])]
    private bool $traite = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cause = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $auteur = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTimeImmutable(); }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    // Getters / Setters
    public function getId(): ?int { return $this->id; }

    public function getSuivi(): ?Suividupreparationdujour { return $this->suivi; }
    public function setSuivi(Suividupreparationdujour $s): self { $this->suivi = $s; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): self { $this->statut = strtoupper($s); return $this; }

    public function isTraite(): bool { return $this->traite; }
    public function setTraite(bool $t): self { $this->traite = $t; return $this; }

    public function getCause(): ?string { return $this->cause; }
    public function setCause(?string $c): self { $this->cause = $c; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $c): self { $this->commentaire = $c; return $this; }

    public function getAuteur(): ?string { return $this->auteur; }
    public function setAuteur(?string $a): self { $this->auteur = $a; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
