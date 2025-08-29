<?php

namespace App\Repository;

use App\Entity\Suividupreparationdujour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SuiviProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Suividupreparationdujour::class);
    }

    /**
     * Top produits sur une période, groupés par CodeProduit, avec agrégats.
     *
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @param string             $sort  'lines' (par défaut) ou 'qty' (somme Nb_art)
     * @param int                $limit
     */
    public function findTopProducts(\DateTimeInterface $start, \DateTimeInterface $end, string $sort = 'lines', int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.CodeProduit AS code_produit')
            ->addSelect('COUNT(s.id) AS lignes')
            ->addSelect('COALESCE(SUM(s.Nb_art), 0) AS total_nb_art')
            ->addSelect('COALESCE(AVG(s.Nb_art), 0) AS avg_nb_art')
            ->addSelect('COALESCE(SUM(s.Nb_col), 0) AS total_nb_col')
            ->addSelect('COALESCE(SUM(s.Nb_Pal), 0) AS total_nb_pal')
            ->addSelect('MAX(s.updatedAt) AS last_at')
            ->addSelect('MIN(s.updatedAt) AS first_at')
            ->where('s.updatedAt IS NOT NULL')
            ->andWhere('s.updatedAt BETWEEN :start AND :end')
            ->andWhere('s.CodeProduit IS NOT NULL AND s.CodeProduit <> \'\'')
            ->groupBy('s.CodeProduit')
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->setMaxResults($limit);

        $order = ($sort === 'qty') ? 'total_nb_art' : 'lignes';
        $qb->orderBy($order, 'DESC');

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Totaux globaux sur la période (pour KPI).
     */
    public function totals(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id) AS lignes')
            ->addSelect('COALESCE(SUM(s.Nb_art), 0) AS total_nb_art')
            ->where('s.updatedAt IS NOT NULL')
            ->andWhere('s.updatedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Distribution des quantités par Nb_art pour un produit donné (sur période).
     * Renvoie nb_art, nombre de lignes, et somme nb_art.
     */
    public function findDistributionByProduct(string $codeProduit, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('s')
            ->select('COALESCE(s.Nb_art, 0) AS nb_art')
            ->addSelect('COUNT(s.id) AS lignes')
            ->addSelect('COALESCE(SUM(s.Nb_art), 0) AS total_nb_art')
            ->where('s.CodeProduit = :code')
            ->andWhere('s.updatedAt IS NOT NULL')
            ->andWhere('s.updatedAt BETWEEN :start AND :end')
            ->groupBy('s.Nb_art')
            ->orderBy('total_nb_art', 'DESC')
            ->setParameter('code',  $codeProduit)
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->getQuery()
            ->getArrayResult();
    }
}
