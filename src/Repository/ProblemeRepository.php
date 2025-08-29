<?php

namespace App\Repository;

use App\Entity\Probleme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Probleme>
 */
class ProblemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Probleme::class);
    }

    /**
     * Trouve tous les problèmes pour un emplacement spécifique
     */
    public function findByEmplacement(string $emplacement): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.emplacement = :emplacement')
            ->setParameter('emplacement', $emplacement)
            ->orderBy('p.dateSignalement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les problèmes actifs (non résolus)
     */
    public function findActiveProblems(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut != :statut')
            ->setParameter('statut', 'resolu')
            ->orderBy('p.dateSignalement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les problèmes par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('p.dateSignalement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les problèmes par palette
     */
    public function findByNopal(string $nopal): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nopal = :nopal')
            ->setParameter('nopal', $nopal)
            ->orderBy('p.dateSignalement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les problèmes par statut
     */
    public function countByStatut(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.statut, COUNT(p.id) as count')
            ->groupBy('p.statut')
            ->getQuery()
            ->getResult();
    }
}