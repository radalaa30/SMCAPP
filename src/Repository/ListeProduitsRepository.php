<?php

namespace App\Repository;

use App\Entity\ListeProduits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ListeProduitsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ListeProduits::class);
    }

    /**
     * Recherche avec filtres + tri + pagination.
     *
     * @return array{0: ListeProduits[], 1: int} [items, total]
     */
    public function search(?string $ref, ?string $des, int $page = 1, int $limit = 20, string $sort = 'ref', string $dir = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($ref !== null && $ref !== '') {
            $qb->andWhere('p.ref LIKE :ref')->setParameter('ref', '%'.$ref.'%');
        }
        if ($des !== null && $des !== '') {
            $qb->andWhere('p.des LIKE :des')->setParameter('des', '%'.$des.'%');
        }

        $allowedSort = ['id','ref','des','uvEnStock','nbrucPal','pinkg'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'ref';
        }
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $qb->orderBy('p.'.$sort, $dir);

        // total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        // pagination
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        return [$items, $total];
    }

    public function findOneByRef(string $ref): ?ListeProduits
    {
        return $this->findOneBy(['ref' => $ref]);
    }
}
