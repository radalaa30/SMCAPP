<?php

namespace App\Entity;

use App\Repository\ImportHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportHistoryRepository::class)]
class ImportHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $importedAt = null;

    #[ORM\Column]
    private ?int $recordCount = 0;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errors = null;

    // Getters et Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getImportedAt(): ?\DateTimeInterface
    {
        return $this->importedAt;
    }

    /**
     * Définit la date d'importation.
     * Accepte une instance \DateTimeInterface, une chaîne de caractères ou null.
     */
    public function setImportedAt(\DateTimeInterface|string|null $importedAt): self
    {
        if (is_string($importedAt)) {
            try {
                $importedAt = new \DateTime($importedAt);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Date invalide pour importedAt : " . $e->getMessage());
            }
        }

        $this->importedAt = $importedAt;
        return $this;
    }

    public function getRecordCount(): ?int
    {
        return $this->recordCount;
    }

    public function setRecordCount(int $recordCount): self
    {
        $this->recordCount = $recordCount;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getErrors(): ?string
    {
        return $this->errors;
    }

    public function setErrors(?string $errors): self
    {
        $this->errors = $errors;
        return $this;
    }
}
