<?php

namespace App\Repository;

use App\Entity\Suividupreparationdujour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PreparateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Preparateur::class);
    }

    public function findByCodeClient($codeClient)
    {
        return $this->createQueryBuilder('p')
            ->where('p.codeClient = :codeClient')
            ->setParameter('codeClient', $codeClient)
            ->getQuery()
            ->getResult();
    }
}