<?php
namespace App\Repository;

use App\Entity\DemandeReappro;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

class DemandeReapproAdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeReappro::class);
    }

    public function findByFilters(array $filters = [], int $page = 1, int $limit = 10): array
    {
        
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.createAt', 'DESC');

        // Filtre par statut
        if (!empty($filters['statut'])) {
            $qb->andWhere('d.statut = :statut')
               ->setParameter('statut', $filters['statut']);
        }

        // Filtre par préparateur
        if (!empty($filters['preparateur'])) {
            $qb->andWhere('d.idPreparateur = :preparateur')
               ->setParameter('preparateur', $filters['preparateur']);
        }

        // Filtre par date
        if (!empty($filters['dateDebut'])) {
            $qb->andWhere('d.createAt >= :dateDebut')
               ->setParameter('dateDebut', new \DateTime($filters['dateDebut']));
        }
        if (!empty($filters['dateFin'])) {
            $qb->andWhere('d.createAt <= :dateFin')
               ->setParameter('dateFin', new \DateTime($filters['dateFin'] . ' 23:59:59'));
        }

        // Pagination
        $firstResult = ($page - 1) * $limit;
        $query = $qb->setFirstResult($firstResult)
                   ->setMaxResults($limit)
                   ->getQuery();

        $paginator = new Paginator($query);
        
        return [
            'items' => $paginator->getIterator()->getArrayCopy(),
            'totalItems' => count($paginator),
            'pageCount' => ceil(count($paginator) / $limit)
        ];
    }

    public function findAllPreparateurs(): array
    {
        return $this->createQueryBuilder('d')
            ->select('DISTINCT d.idPreparateur')
            ->orderBy('d.idPreparateur', 'ASC')
            ->getQuery()
            ->getResult();
    }
}