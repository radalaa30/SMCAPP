<?php

namespace App\Repository;

use App\Entity\Demande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demande>
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande::class);
    }


    /**
     * @return Demande[] Returns an array of YourEntity objects
     */
    public function findByExampleFieldExcludingValue($value): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.zone != :val')  // Exclure les enregistrements ayant la valeur spécifique
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
}
