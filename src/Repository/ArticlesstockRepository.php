<?php
namespace App\Repository;

use App\Entity\Articlesstock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Articlesstock>
 */
class ArticlesstockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Articlesstock::class);
    }

    /**
     * Trouve un produit pour réapprovisionnement par sa référence
     * Utilisé dans le contrôleur ReapproController
     */
    public function findProduitPourReappro(string $reference): ?Articlesstock
    {
        return $this->createQueryBuilder('p')
            ->where('p.reference = :reference')
            ->andWhere('p.picking IS NOT NULL')
            ->andWhere('p.picking <> :empty')
            ->setParameter('reference', $reference)
            ->setParameter('empty', '')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les produits par famille de stockage
     */
    public function findByFamilleStockage(string $famille): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.familleStockage = :famille')
            ->setParameter('famille', $famille)
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les produits en dessous du seuil de réapprovisionnement
     */
    public function findBelowSeuilReappro(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.uvEnStock < p.seuilReappro')
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les produits par désignation
     */
    public function findByDesignation(string $search): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.designation LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un produit par son SKU
     */
    public function findOneBySku(string $sku): ?Articlesstock
    {
        return $this->createQueryBuilder('p')
            ->where('p.sku = :sku')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les produits par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statutArticle = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Recherche un produit par sa référence et retourne le résultat sous forme de tableau
     * Avec option de correspondance exacte ou partielle
     */
    public function findByRefAsArray(string $reference, bool $exactMatch = true): array
    {
        $qb = $this->createQueryBuilder('p');
        
        if ($exactMatch) {
            $qb->where('p.reference = :reference')
               ->setParameter('reference', $reference);
        } else {
            $qb->where('p.reference LIKE :reference')
               ->setParameter('reference', '%' . $reference . '%');
        }
        
        return $qb->getQuery()->getArrayResult();
    }
    
    /**
     * Méthode de débogage pour vérifier les références disponibles
     * Retourne un échantillon limité de références
     */
    public function findSampleReferences(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.reference')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }
    
    /**
     * Trouve tous les produits disponibles pour le picking
     */
    public function findAllAvailableForPicking(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.picking IS NOT NULL')
            ->andWhere('p.picking <> :empty')
            ->setParameter('empty', '')
            ->orderBy('p.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }
}