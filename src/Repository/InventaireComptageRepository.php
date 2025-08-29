<?php

namespace App\Repository;

use App\Entity\InventaireComptage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventaireComptage>
 */
class InventaireComptageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventaireComptage::class);
    }

    /**
     * Trouve les comptages par code produit et session
     */
    public function findByCodeprodAndSession(string $codeprod, string $session): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.codeprod = :codeprod')
            ->andWhere('c.session_inventaire = :session')
            ->setParameter('codeprod', $codeprod)
            ->setParameter('session', $session)
            ->orderBy('c.emplacement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptages avec écarts pour un produit
     */
    public function findEcartsForProduct(string $codeprod, string $session): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.codeprod = :codeprod')
            ->andWhere('c.session_inventaire = :session')
            ->andWhere('c.ecart != 0')
            ->setParameter('codeprod', $codeprod)
            ->setParameter('session', $session)
            ->orderBy('ABS(c.ecart)', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques de comptage pour un produit
     */
    public function getStatistiquesComptage(string $codeprod, string $session): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('
                COUNT(c.id) as total_emplacements,
                SUM(CASE WHEN c.valide = true THEN 1 ELSE 0 END) as emplacements_valides,
                SUM(CASE WHEN c.ecart > 0 THEN 1 ELSE 0 END) as surplus,
                SUM(CASE WHEN c.ecart < 0 THEN 1 ELSE 0 END) as manquants,
                SUM(CASE WHEN c.ecart = 0 THEN 1 ELSE 0 END) as conformes,
                SUM(c.ecart) as ecart_total,
                SUM(c.qte_theorique) as total_theorique,
                SUM(c.qte_comptee) as total_compte,
                SUM(CASE WHEN c.commentaire IS NOT NULL AND c.commentaire != \'\' THEN 1 ELSE 0 END) as avec_commentaires
            ')
            ->andWhere('c.codeprod = :codeprod')
            ->andWhere('c.session_inventaire = :session')
            ->setParameter('codeprod', $codeprod)
            ->setParameter('session', $session);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Trouve les comptages non validés
     */
    public function findNonValides(string $codeprod, string $session): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.codeprod = :codeprod')
            ->andWhere('c.session_inventaire = :session')
            ->andWhere('c.valide = false')
            ->setParameter('codeprod', $codeprod)
            ->setParameter('session', $session)
            ->orderBy('c.emplacement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les écarts majeurs (> seuil %)
     */
    public function findEcartsMajeurs(string $codeprod, string $session, float $seuilPourcentage = 10.0): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.codeprod = :codeprod')
            ->andWhere('c.session_inventaire = :session')
            ->andWhere('(ABS(c.ecart) / c.qte_theorique * 100) > :seuil')
            ->andWhere('c.qte_theorique > 0')
            ->setParameter('codeprod', $codeprod)
            ->setParameter('session', $session)
            ->setParameter('seuil', $seuilPourcentage)
            ->orderBy('ABS(c.ecart)', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le résumé global d'une session
     */
    public function getResumeSession(string $session): array
    {
        return $this->createQueryBuilder('c')
            ->select('
                c.codeprod,
                c.dsignprod,
                COUNT(c.id) as nb_emplacements,
                SUM(CASE WHEN c.valide = true THEN 1 ELSE 0 END) as nb_valides,
                SUM(CASE WHEN c.ecart != 0 THEN 1 ELSE 0 END) as nb_avec_ecarts,
                SUM(c.ecart) as ecart_total,
                SUM(c.qte_theorique) as total_theorique,
                SUM(c.qte_comptee) as total_compte,
                MAX(c.date_comptage) as derniere_modification
            ')
            ->andWhere('c.session_inventaire = :session')
            ->setParameter('session', $session)
            ->groupBy('c.codeprod, c.dsignprod')
            ->orderBy('c.codeprod', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les comptages d'une session pour un produit
     */
    public function deleteComptagesSession(string $codeprod, string $session): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.codeprod = :codeprod')
            ->andWhere('c.session_inventaire = :session')
            ->setParameter('codeprod', $codeprod)
            ->setParameter('session', $session)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve tous les comptages d'une session avec pagination
     */
    public function findBySessionWithPagination(string $session, int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.session_inventaire = :session')
            ->setParameter('session', $session)
            ->orderBy('c.codeprod', 'ASC')
            ->addOrderBy('c.emplacement', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les comptages d'une session
     */
    public function countBySession(string $session): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.session_inventaire = :session')
            ->setParameter('session', $session)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les comptages par type d'écart
     */
    public function findByTypeEcart(string $session, string $typeEcart = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.session_inventaire = :session')
            ->setParameter('session', $session);

        if ($typeEcart) {
            $qb->andWhere('c.type_ecart = :type_ecart')
               ->setParameter('type_ecart', $typeEcart);
        }

        return $qb->orderBy('c.date_comptage', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Statistiques globales des comptages
     */
    public function getStatistiquesGlobales(): array
    {
        return $this->createQueryBuilder('c')
            ->select('
                COUNT(c.id) as total_comptages,
                COUNT(DISTINCT c.codeprod) as produits_comptes,
                COUNT(DISTINCT c.session_inventaire) as sessions_actives,
                SUM(CASE WHEN c.valide = true THEN 1 ELSE 0 END) as comptages_valides,
                SUM(CASE WHEN c.ecart != 0 THEN 1 ELSE 0 END) as avec_ecarts,
                AVG(ABS(c.ecart)) as ecart_moyen,
                SUM(CASE WHEN c.ecart > 0 THEN c.ecart ELSE 0 END) as total_surplus,
                SUM(CASE WHEN c.ecart < 0 THEN ABS(c.ecart) ELSE 0 END) as total_manquants
            ')
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Top des produits avec le plus d'écarts
     */
    public function getTopProduitsEcarts(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('
                c.codeprod,
                c.dsignprod,
                COUNT(c.id) as nb_comptages,
                SUM(CASE WHEN c.ecart != 0 THEN 1 ELSE 0 END) as nb_ecarts,
                SUM(ABS(c.ecart)) as total_ecart_absolu,
                AVG(ABS(c.ecart)) as ecart_moyen
            ')
            ->where('c.ecart != 0')
            ->groupBy('c.codeprod, c.dsignprod')
            ->orderBy('SUM(ABS(c.ecart))', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Comptages récents (derniers X jours)
     */
    public function findRecentComptages(int $jours = 7): array
    {
        $dateLimit = new \DateTimeImmutable("-{$jours} days");
        
        return $this->createQueryBuilder('c')
            ->andWhere('c.date_comptage >= :date_limit')
            ->setParameter('date_limit', $dateLimit)
            ->orderBy('c.date_comptage', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptages par opérateur
     */
    public function findByOperateur(string $operateur, string $session = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.operateur = :operateur')
            ->setParameter('operateur', $operateur);
            
        if ($session) {
            $qb->andWhere('c.session_inventaire = :session')
               ->setParameter('session', $session);
        }

        return $qb->orderBy('c.date_comptage', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Statistiques par opérateur
     */
    public function getStatistiquesOperateur(string $operateur): array
    {
        return $this->createQueryBuilder('c')
            ->select('
                COUNT(c.id) as total_comptages,
                SUM(CASE WHEN c.valide = true THEN 1 ELSE 0 END) as comptages_valides,
                SUM(CASE WHEN c.ecart != 0 THEN 1 ELSE 0 END) as avec_ecarts,
                AVG(ABS(c.ecart)) as ecart_moyen,
                COUNT(DISTINCT c.codeprod) as produits_comptes,
                COUNT(DISTINCT c.session_inventaire) as sessions
            ')
            ->andWhere('c.operateur = :operateur')
            ->setParameter('operateur', $operateur)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Exporte les données de comptage pour une session
     */
    public function getDataForExport(string $session, string $codeprod = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.session_inventaire = :session')
            ->setParameter('session', $session);
            
        if ($codeprod) {
            $qb->andWhere('c.codeprod = :codeprod')
               ->setParameter('codeprod', $codeprod);
        }

        return $qb->orderBy('c.codeprod', 'ASC')
                  ->addOrderBy('c.emplacement', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Sauvegarde optimisée en lot
     */
    public function saveBatch(array $comptages): void
    {
        $batchSize = 20;
        $i = 0;
        
        foreach ($comptages as $comptage) {
            $this->getEntityManager()->persist($comptage);
            
            if (($i % $batchSize) === 0) {
                $this->getEntityManager()->flush();
            }
            ++$i;
        }
        
        $this->getEntityManager()->flush();
    }

    /**
     * Recherche avancée avec filtres multiples
     */
    public function searchAvancee(array $criteres): array
    {
        $qb = $this->createQueryBuilder('c');

        if (isset($criteres['codeprod']) && !empty($criteres['codeprod'])) {
            $qb->andWhere('c.codeprod LIKE :codeprod')
               ->setParameter('codeprod', '%' . $criteres['codeprod'] . '%');
        }

        if (isset($criteres['emplacement']) && !empty($criteres['emplacement'])) {
            $qb->andWhere('c.emplacement LIKE :emplacement')
               ->setParameter('emplacement', '%' . $criteres['emplacement'] . '%');
        }

        if (isset($criteres['zone']) && !empty($criteres['zone'])) {
            $qb->andWhere('c.zone = :zone')
               ->setParameter('zone', $criteres['zone']);
        }

        if (isset($criteres['operateur']) && !empty($criteres['operateur'])) {
            $qb->andWhere('c.operateur = :operateur')
               ->setParameter('operateur', $criteres['operateur']);
        }

        if (isset($criteres['avec_ecarts']) && $criteres['avec_ecarts']) {
            $qb->andWhere('c.ecart != 0');
        }

        if (isset($criteres['valide']) && $criteres['valide'] !== null) {
            $qb->andWhere('c.valide = :valide')
               ->setParameter('valide', $criteres['valide']);
        }

        if (isset($criteres['date_debut']) && $criteres['date_debut']) {
            $qb->andWhere('c.date_comptage >= :date_debut')
               ->setParameter('date_debut', $criteres['date_debut']);
        }

        if (isset($criteres['date_fin']) && $criteres['date_fin']) {
            $qb->andWhere('c.date_comptage <= :date_fin')
               ->setParameter('date_fin', $criteres['date_fin']);
        }

        return $qb->orderBy('c.date_comptage', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}