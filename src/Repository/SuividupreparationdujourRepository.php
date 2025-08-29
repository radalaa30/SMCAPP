<?php
namespace App\Repository;

use App\Entity\Suividupreparationdujour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * Repository pour l'entité Suividupreparationdujour
 * 
 * @extends ServiceEntityRepository<Suividupreparationdujour>
 */
class SuividupreparationdujourRepository extends ServiceEntityRepository
{
    private LoggerInterface $logger;
    
    public function __construct(ManagerRegistry $registry, LoggerInterface $logger = null)
    {
        parent::__construct($registry, Suividupreparationdujour::class);
        $this->logger = $logger;
    }

    /**
     * Trouve les suivis pour un numéro de BL spécifique avec des adresses 
     * se terminant par -01, -02, -03 ou -04, ne contenant pas "ETAG" et dont Flasher est vide
     * 
     * @param string $numBl Le numéro de BL à rechercher
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findByNoBLWithSpecificAddresses(string $numBl)
    {
        try {
            $qb = $this->createQueryBuilder('s');
            return $qb->where('s.No_Bl = :numBl')
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->isNull('s.Flasher'),
                    $qb->expr()->eq('s.Flasher', ':emptyString')
                ))
                ->andWhere($qb->expr()->notLike('s.Adresse', ':notContainsETAG'))
                ->andWhere($qb->expr()->orX(
                    $qb->expr()->like('s.Adresse', ':suffix01'),
                    $qb->expr()->like('s.Adresse', ':suffix02'),
                    $qb->expr()->like('s.Adresse', ':suffix03'),
                    $qb->expr()->like('s.Adresse', ':suffix04')
                ))
                ->setParameter('numBl', $numBl)
                ->setParameter('emptyString', '')
                ->setParameter('notContainsETAG', '%ETAG%')
                ->setParameter('suffix01', '%-01')
                ->setParameter('suffix02', '%-02')
                ->setParameter('suffix03', '%-03')
                ->setParameter('suffix04', '%-04')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche des suivis par numéro de BL et adresses spécifiques', [
                    'numBl' => $numBl,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Trouve les suivis avec des adresses spécifiques (terminant par -01, -02, -03, -04)
     * et excluant les adresses commençant par 'C2S:G'
     * 
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findBySpecificAddressPattern()
    {
        try {
            $qb = $this->createQueryBuilder('s');
            
            return $qb->where($qb->expr()->orX(
                    $qb->expr()->like('s.Adresse', ':pattern1'),
                    $qb->expr()->like('s.Adresse', ':pattern2'),
                    $qb->expr()->like('s.Adresse', ':pattern3'),
                    $qb->expr()->like('s.Adresse', ':pattern4')
                ))
                ->andWhere('CONCAT(s.Zone, \':\', s.Adresse) NOT LIKE :exclude')
                ->setParameter('pattern1', '%-01')
                ->setParameter('pattern2', '%-02')
                ->setParameter('pattern3', '%-03')
                ->setParameter('pattern4', '%-04')
                ->setParameter('exclude', 'C2S:G%')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche des suivis par pattern d\'adresse', [
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Trouve les suivis par code produit
     * 
     * @param string $codeProduit Le code produit à rechercher
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findByCodeProduit(string $codeProduit)
    {
        try {
            return $this->createQueryBuilder('s')
                ->where('s.CodeProduit = :codeProduit') // Corrigé: Code_produit -> CodeProduit
                ->setParameter('codeProduit', $codeProduit)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche des suivis par code produit', [
                    'codeProduit' => $codeProduit,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Trouve les suivis en attente
     * 
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findPending()
    {
        try {
            return $this->createQueryBuilder('s')
                ->where('s.Statut_Cde = :statut') // Corrigé: Statut_preparation -> Statut_Cde
                ->setParameter('statut', 'En attente')
                ->orderBy('s.No_Bl', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche des suivis en attente', [
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Trouve les suivis par date de livraison
     * 
     * @param \DateTimeInterface $date La date à rechercher
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findByDate(\DateTimeInterface $date)
    {
        try {
            $dateString = $date->format('Y-m-d');
            
            return $this->createQueryBuilder('s')
                ->where('DATE(s.Date_liv) = :date') // Corrigé: Date_preparation -> Date_liv
                ->setParameter('date', $dateString)
                ->orderBy('s.No_Bl', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche des suivis par date', [
                    'date' => $date->format('Y-m-d'),
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Trouve les suivis créés entre deux dates
     * 
     * @param \DateTimeInterface $startDate Date de début
     * @param \DateTimeInterface $endDate Date de fin
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findByCreatedDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate)
    {
        try {
            return $this->createQueryBuilder('s')
                ->where('s.created BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->orderBy('s.created', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche par date de création', [
                    'startDate' => $startDate->format('Y-m-d'),
                    'endDate' => $endDate->format('Y-m-d'),
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Trouve les suivis validés
     * 
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findValidated()
    {
        try {
            return $this->createQueryBuilder('s')
                ->where('s.valider IS NOT NULL')
                ->orderBy('s.valider', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche des suivis validés', [
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Trouve les suivis non validés
     * 
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findNotValidated()
    {
        try {
            return $this->createQueryBuilder('s')
                ->where('s.valider IS NULL')
                ->orderBy('s.created', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche des suivis non validés', [
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Trouve les suivis par transporteur
     * 
     * @param string $transporteur Le transporteur à rechercher
     * @return Suividupreparationdujour[] Tableau des résultats
     */
    public function findByTransporteur(string $transporteur)
    {
        try {
            return $this->createQueryBuilder('s')
                ->where('s.Transporteur = :transporteur')
                ->setParameter('transporteur', $transporteur)
                ->orderBy('s.Date_liv', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors de la recherche des suivis par transporteur', [
                    'transporteur' => $transporteur,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Compte les suivis par statut
     * 
     * @return array Tableau associatif des comptages par statut
     */
    public function countByStatus()
    {
        try {
            $result = $this->createQueryBuilder('s')
                ->select('s.Statut_Cde as statut, COUNT(s.id) as count')
                ->groupBy('s.Statut_Cde')
                ->getQuery()
                ->getResult();

            // Convertir en tableau associatif
            $counts = [];
            foreach ($result as $row) {
                $counts[$row['statut']] = (int)$row['count'];
            }

            return $counts;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Erreur lors du comptage des suivis par statut', [
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }
        /**
     * Retourne les lignes de Suividupreparationdujour pour un CodeProduit (= ref produit),
     * avec pagination. Renvoie [items, total].
     */
    public function findByCodeProduitPaginated(string $codeProduit, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.CodeProduit = :code')
            ->setParameter('code', trim($codeProduit))
            ->orderBy('s.updatedAt', 'DESC');

        // total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(s.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        // page
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        return [$items, $total];
    }
    public function findByCodeProduitFilteredPaginated(string $codeProduit, array $f, int $page = 1, int $limit = 20): array
{
    $qb = $this->createQueryBuilder('s')
        ->where('s.CodeProduit = :code')
        ->setParameter('code', trim($codeProduit));

    // LIKE sur les champs texte (contient)
    if (!empty($f['noBl']))         { $qb->andWhere('s.No_Bl LIKE :noBl')->setParameter('noBl', '%'.$f['noBl'].'%'); }
    if (!empty($f['noCmd']))        { $qb->andWhere('s.No_Cmd LIKE :noCmd')->setParameter('noCmd', '%'.$f['noCmd'].'%'); }
    if (!empty($f['client']))       { $qb->andWhere('s.Client LIKE :client')->setParameter('client', '%'.$f['client'].'%'); }
    if (!empty($f['codeClient']))   { $qb->andWhere('s.Code_Client LIKE :codeClient')->setParameter('codeClient', '%'.$f['codeClient'].'%'); }
    if (!empty($f['zone']))         { $qb->andWhere('s.Zone LIKE :zone')->setParameter('zone', '%'.$f['zone'].'%'); }
    if (!empty($f['adresse']))      { $qb->andWhere('s.Adresse LIKE :adresse')->setParameter('adresse', '%'.$f['adresse'].'%'); }
    if (!empty($f['flasher']))      { $qb->andWhere('s.Flasher LIKE :flasher')->setParameter('flasher', '%'.$f['flasher'].'%'); }
    if (!empty($f['preparateur']))  { $qb->andWhere('s.Preparateur LIKE :preparateur')->setParameter('preparateur', '%'.$f['preparateur'].'%'); }
    if (!empty($f['transporteur'])) { $qb->andWhere('s.Transporteur LIKE :transporteur')->setParameter('transporteur', '%'.$f['transporteur'].'%'); }

    // Dates
    if (!empty($f['maj_from'])) { $qb->andWhere('s.updatedAt >= :majFrom')->setParameter('majFrom', $f['maj_from']); }
    if (!empty($f['maj_to']))   { $qb->andWhere('s.updatedAt <= :majTo')  ->setParameter('majTo',   $f['maj_to']); }
    if (!empty($f['liv_from'])) { $qb->andWhere('s.Date_liv >= :livFrom')->setParameter('livFrom', $f['liv_from']); }
    if (!empty($f['liv_to']))   { $qb->andWhere('s.Date_liv <= :livTo')  ->setParameter('livTo',   $f['liv_to']); }

    // Total
    $countQb = clone $qb;
    $total = (int) $countQb->select('COUNT(s.id)')
        ->resetDQLPart('orderBy')
        ->getQuery()
        ->getSingleScalarResult();

    // Page + tri (par défaut : updatedAt desc, puis Date_liv desc)
    $qb->orderBy('s.updatedAt', 'DESC')->addOrderBy('s.Date_liv', 'DESC')
       ->setFirstResult(($page - 1) * $limit)
       ->setMaxResults($limit);

    $items = $qb->getQuery()->getResult();

    return [$items, $total];
}

}