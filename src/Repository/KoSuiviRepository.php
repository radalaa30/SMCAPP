<?php

namespace App\Repository;

use App\Entity\KoSuivi;
use App\Entity\Suividupreparationdujour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class KoSuiviRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KoSuivi::class);
    }

    public function findLatestFor(Suividupreparationdujour $suivi): ?KoSuivi
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.suivi = :s')->setParameter('s', $suivi)
            ->orderBy('k.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Dernier KoSuivi pour un lot d’IDs Suivi (map idSuivi => KoSuivi)
     */
    public function findLatestForIds(array $suiviIds): array
    {
        if (!$suiviIds) {
            return [];
        }

        $dql = '
            SELECT ks
            FROM App\Entity\KoSuivi ks
            WHERE ks.suivi IN (:ids)
              AND ks.createdAt = (
                 SELECT MAX(ks2.createdAt)
                 FROM App\Entity\KoSuivi ks2
                 WHERE ks2.suivi = ks.suivi
              )
        ';

        // ✅ Utiliser l'EntityManager via getEntityManager()
        $em = $this->getEntityManager();

        $list = $em->createQuery($dql)
            ->setParameter('ids', $suiviIds)
            ->getResult();

        $map = [];
        foreach ($list as $ks) {
            $map[$ks->getSuivi()->getId()] = $ks;
        }

        return $map;
    }
}
