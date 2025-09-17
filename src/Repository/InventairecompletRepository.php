<?php

namespace App\Repository;

use App\Entity\Inventairecomplet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inventairecomplet>
 */
class InventairecompletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inventairecomplet::class);
    }

    /**
     * Trouve tous les emplacements pour une cellule et une allée spécifiques
     * 
     * @param string $cellule Le code de cellule (C2, C3, C4)
     * @param string $allee La lettre de l'allée (A-H)
     * @return Inventairecomplet[]
     */
    public function findEmplacementsByCelluleAndAllee(string $cellule, string $allee): array
    {
        // Format d'emplacement attendu: "C2:A-01-00" pour Cellule C2, Allée A, Position 01, Niveau 00
        $pattern = $cellule . ':' . $allee . '-%';
        
        return $this->createQueryBuilder('i')
            ->where('i.emplacement LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->orderBy('i.emplacement', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Vérifie si un emplacement existe
     */
    public function emplacementExists(string $emplacement): bool
    {
        return count($this->findBy(['emplacement' => $emplacement])) > 0;
    }
    
    /**
     * Récupère toutes les cellules disponibles avec leur nombre d'emplacements
     */
    public function findCellules(): array
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUBSTRING(i.emplacement, 1, 2) as cellule, COUNT(i.id) as nombre')
            ->groupBy('cellule')
            ->getQuery()
            ->getResult();
            
        $cellules = [];
        foreach ($result as $row) {
            $cellules[$row['cellule']] = $row['nombre'];
        }
        
        return $cellules;
    }
    
    /**
     * Récupère toutes les allées d'une cellule avec leur nombre d'emplacements
     */
    public function findAlleesByCellule(string $cellule): array
    {
        $pattern = $cellule . ':%';
        
        $result = $this->createQueryBuilder('i')
            ->select('SUBSTRING(i.emplacement, 4, 1) as allee, COUNT(i.id) as nombre')
            ->where('i.emplacement LIKE :pattern')
            ->setParameter('pattern', $pattern)
            ->groupBy('allee')
            ->orderBy('allee', 'ASC')
            ->getQuery()
            ->getResult();
            
        $allees = [];
        foreach ($result as $row) {
            $allees[$row['allee']] = $row['nombre'];
        }
        
        return $allees;
    }
    
    /**
     * Compte le nombre d'emplacements occupés pour une cellule et une allée
     */
    public function countOccupiedEmplacements(string $cellule, string $allee = null): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.emplacement LIKE :pattern')
            ->andWhere('i.urdispo > 0 OR i.ucdispo > 0');
            
        if ($allee !== null) {
            $pattern = $cellule . ':' . $allee . '-%';
        } else {
            $pattern = $cellule . ':%';
        }
        
        $qb->setParameter('pattern', $pattern);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Compte le nombre d'emplacements bloqués pour une cellule et une allée
     */
    public function countBlockedEmplacements(string $cellule, string $allee = null): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.emplacement LIKE :pattern')
            ->andWhere('i.urbloquee > 0');
            
        if ($allee !== null) {
            $pattern = $cellule . ':' . $allee . '-%';
        } else {
            $pattern = $cellule . ':%';
        }
        
        $qb->setParameter('pattern', $pattern);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Recherche des emplacements par code produit
     */
    public function findByCodeProduit(string $codeProduit): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.codeprod = :codeprod')
            ->setParameter('codeprod', $codeProduit)
            ->orderBy('i.emplacement', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Recherche des emplacements par numéro de palette
     */
    public function findByNoPalette(string $noPalette): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.nopal = :nopal')
            ->setParameter('nopal', $noPalette)
            ->orderBy('i.emplacement', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /* ===========================
     * 🔽 AJOUT POUR LA “MASSE”
     * =========================== */

    /**
     * Récupère uniquement les enregistrements dont l'emplacement
     * commence par une des bases fournies (ex: 'M1_A_095' OU 'M1_A_095-%').
     *
     * @param string[] $bases Bases "M1_A_095", "M1_B_200", …
     * @return Inventairecomplet[]
     */
    public function findByBasePrefixesIn(array $bases): array
    {
        if (empty($bases)) return [];

        $chunkSize = 120;
        $results = [];

        foreach (array_chunk($bases, $chunkSize) as $chunk) {
            $qb = $this->createQueryBuilder('i');
            $orX = $qb->expr()->orX();

            foreach ($chunk as $idx => $base) {
                // On matche l'égalité stricte ET le préfixe avec "-".
                $paramEq   = 'eq' . $idx;
                $paramLike = 'lk' . $idx;
                $orX->add("(i.emplacement = :$paramEq OR i.emplacement LIKE :$paramLike)");
                $qb->setParameter($paramEq, $base);
                $qb->setParameter($paramLike, $base . '-%');
            }

            $res = $qb->where($orX)->getQuery()->getResult();
            if (!empty($res)) $results = array_merge($results, $res);
        }

        return $results;
    }
}
