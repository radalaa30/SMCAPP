<?php

namespace App\Repository;

use App\Entity\Blencours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Blencours>
 *
 * @method Blencours|null find($id, $lockMode = null, $lockVersion = null)
 * @method Blencours|null findOneBy(array $criteria, array $orderBy = null)
 * @method Blencours[]    findAll()
 * @method Blencours[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BlencoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Blencours::class);
    }

    /**
     * Enregistre un BL en base de données
     */
    public function save(Blencours $blencours, bool $flush = true): void
    {
        $this->getEntityManager()->persist($blencours);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un BL de la base de données
     */
    public function remove(Blencours $blencours, bool $flush = true): void
    {
        $this->getEntityManager()->remove($blencours);
        
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Marque un BL comme "Traité"
     */
    public function markAsCompleted(Blencours $blencours): void
    {
        $blencours->setStatut('Traité');
        $this->save($blencours);
    }

    /**
     * Récupère tous les BL avec filtres et tri
     */
    public function findAllWithFilters(array $filters = [], string $sortField = 'adddate', string $sortDirection = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.' . $sortField, $sortDirection);

        $this->applyFilters($qb, $filters);

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère tous les BL avec pagination, filtres et tri
     */
    public function findAllPaginated(int $page = 1, int $limit = 10, array $filters = [], string $sortField = 'adddate', string $sortDirection = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.' . $sortField, $sortDirection)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $this->applyFilters($qb, $filters);
        
        return [
            'items' => $qb->getQuery()->getResult(),
            'totalItems' => $this->countWithFilters($filters)
        ];
    }

    /**
     * Compte le nombre total de BL avec filtres
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)');
        
        $this->applyFilters($qb, $filters);
        
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Recherche des BL par numéro ou statut (pour l'autocomplétion)
     */
    public function searchByNumBLOrStatut(string $term): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.numBl LIKE :term')
            ->orWhere('b.statut LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('b.adddate', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les statistiques des BL
     */
    public function getStats(): array
    {
        $totalCount = $this->count([]);
        
        $enAttente = $this->count(['statut' => 'En attente']);
        $enCours = $this->count(['statut' => 'En cours']);
        $traite = $this->count(['statut' => 'Traité']);
        
        $pickingokCount = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.Pickingok = :pickingok')
            ->setParameter('pickingok', true)
            ->getQuery()
            ->getSingleScalarResult();
            
        $pickingnokCount = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.Pickingnok = :pickingnok')
            ->setParameter('pickingnok', true)
            ->getQuery()
            ->getSingleScalarResult();
            
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        $addedToday = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.adddate >= :today')
            ->andWhere('b.adddate < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
            
        return [
            'total' => $totalCount,
            'enAttente' => $enAttente,
            'enCours' => $enCours,
            'traite' => $traite,
            'pickingok' => $pickingokCount,
            'pickingnok' => $pickingnokCount,
            'addedToday' => $addedToday
        ];
    }

    /**
     * Applique les filtres à la requête
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['numBl'])) {
            $qb->andWhere('b.numBl LIKE :numBl')
                ->setParameter('numBl', '%' . $filters['numBl'] . '%');
        }
        
        if (!empty($filters['statut'])) {
            $qb->andWhere('b.statut = :statut')
                ->setParameter('statut', $filters['statut']);
        }
        
        if (isset($filters['Pickingok'])) {
            $qb->andWhere('b.Pickingok = :Pickingok')
                ->setParameter('Pickingok', $filters['Pickingok']);
        }
        
        if (isset($filters['Pickingnok'])) {
            $qb->andWhere('b.Pickingnok = :Pickingnok')
                ->setParameter('Pickingnok', $filters['Pickingnok']);
        }
        
        if (!empty($filters['dateMin'])) {
            $qb->andWhere('b.adddate >= :dateMin')
                ->setParameter('dateMin', new \DateTime($filters['dateMin']));
        }
        
        if (!empty($filters['dateMax'])) {
            $qb->andWhere('b.adddate <= :dateMax')
                ->setParameter('dateMax', new \DateTime($filters['dateMax']));
        }
    }
    /**
     * Supprimer tous les BL
     */
    public function clearAll(): int
    {
        $entityManager = $this->getEntityManager();
        
        // Compter avant suppression
        $count = $this->count([]);
        
        // Supprimer tous les enregistrements
        $query = $entityManager->createQuery('DELETE FROM App\Entity\Blencours');
        $query->execute();
        
        return $count;
    }

    /**
 * Supprimer les BL par statut
 */
public function deleteByStatus(string $status): int
{
    $entityManager = $this->getEntityManager();
    
    // Compter avant suppression
    $count = $this->count(['statut' => $status]);
    
    // Supprimer par statut
    $query = $entityManager->createQuery('DELETE FROM App\Entity\Blencours b WHERE b.statut = :status');
    $query->setParameter('status', $status);
    $query->execute();
    
    return $count;
}
}