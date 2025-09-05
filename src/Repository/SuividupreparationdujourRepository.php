<?php

namespace App\Repository;

use App\Entity\Suividupreparationdujour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Suividupreparationdujour>
 */
class SuividupreparationdujourRepository extends ServiceEntityRepository
{
    private ?LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger = null)
    {
        parent::__construct($registry, Suividupreparationdujour::class);
        $this->logger = $logger;
    }

    /**
     * Petit utilitaire réutilisable pour exclure le transporteur LIV.
     * - Exclut exactement "LIV" (insensible à la casse).
     *   Si tu veux exclure tout ce qui commence par "LIV", utilise la variante "NOT LIKE 'LIV%'" commentée ci-dessous.
     */
    private function excludeLIV(QueryBuilder $qb, string $alias = 's'): void
    {
        // Exclusion stricte de LIV
        $qb->andWhere(sprintf('(%s.Transporteur IS NULL OR UPPER(%s.Transporteur) <> :liv)', $alias, $alias))
           ->setParameter('liv', 'LIV');

        // Variante si besoin : exclure tout ce qui commence par "LIV"
        // $qb->andWhere(sprintf('(%s.Transporteur IS NULL OR UPPER(%s.Transporteur) NOT LIKE :liv)', $alias, $alias))
        //    ->setParameter('liv', 'LIV%');
    }

    /**
     * Trouve les suivis pour un numéro de BL spécifique avec des adresses se terminant par -01, -02, -03 ou -04,
     * ne contenant pas "ETAG" et dont Flasher est vide
     */
    public function findByNoBLWithSpecificAddresses(string $numBl)
    {
        try {
            $qb = $this->createQueryBuilder('s');
            $qb->where('s.No_Bl = :numBl')
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
               ->setParameter('suffix04', '%-04');

            // Si tu veux aussi exclure LIV ici, décommente la ligne suivante :
            // $this->excludeLIV($qb, 's');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findByNoBLWithSpecificAddresses', [
                'numBl' => $numBl,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trouve les suivis avec des adresses spécifiques (terminant par -01, -02, -03, -04)
     * et excluant les adresses commençant par 'C2S:G'
     */
    public function findBySpecificAddressPattern()
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where($qb->expr()->orX(
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
                ->setParameter('exclude', 'C2S:G%');

            // Exclure LIV ici car c’est typiquement une recherche “globale”
            $this->excludeLIV($qb, 's');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findBySpecificAddressPattern', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trouve les suivis par code produit
     */
    public function findByCodeProduit(string $codeProduit)
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.CodeProduit = :codeProduit')
                ->setParameter('codeProduit', $codeProduit);

            // Si tu veux exclure LIV aussi dans cette vue, décommente :
            // $this->excludeLIV($qb, 's');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findByCodeProduit', [
                'codeProduit' => $codeProduit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trouve les suivis en attente (Statut_Cde = "En attente")
     */
    public function findPending()
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.Statut_Cde = :statut')
                ->setParameter('statut', 'En attente')
                ->orderBy('s.No_Bl', 'ASC');

            // Exclure LIV si nécessaire pour tes écrans
            $this->excludeLIV($qb, 's');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findPending', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trouve les suivis par date de livraison (Date_liv)
     */
    public function findByDate(\DateTimeInterface $date)
    {
        try {
            $dateString = $date->format('Y-m-d');
            $qb = $this->createQueryBuilder('s')
                ->where('DATE(s.Date_liv) = :date')
                ->setParameter('date', $dateString)
                ->orderBy('s.No_Bl', 'ASC');

            // Exclure LIV si nécessaire
            $this->excludeLIV($qb, 's');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findByDate', [
                'date'  => $date->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trouve les suivis créés entre deux dates (champ "created")
     */
    public function findByCreatedDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate)
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.created BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->orderBy('s.created', 'DESC');

            // Exclure LIV si nécessaire
            $this->excludeLIV($qb, 's');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findByCreatedDateRange', [
                'startDate' => $startDate->format('Y-m-d H:i:s'),
                'endDate'   => $endDate->format('Y-m-d H:i:s'),
                'error'     => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trouve les suivis validés (valider non nul)
     */
    public function findValidated()
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.valider IS NOT NULL')
                ->orderBy('s.valider', 'DESC');

            // Exclure LIV si nécessaire
            // $this->excludeLIV($qb, 's');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findValidated', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trouve les suivis non validés (valider nul)
     */
    public function findNotValidated()
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.valider IS NULL')
                ->orderBy('s.created', 'DESC');

            // Exclure LIV si nécessaire
            // $this->excludeLIV($qb, 's');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findNotValidated', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Trouve les suivis par transporteur (si tu appelles cette méthode avec "LIV", elle retournera naturellement des lignes LIV.
     * Garde-la telle quelle pour un comportement "exact" par transporteur.)
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
            $this->logger?->error('Erreur findByTransporteur', [
                'transporteur' => $transporteur,
                'error'        => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Compte les suivis par statut
     */
    public function countByStatus()
    {
        try {
            $result = $this->createQueryBuilder('s')
                ->select('s.Statut_Cde as statut, COUNT(s.id) as count')
                ->groupBy('s.Statut_Cde')
                ->getQuery()
                ->getResult();

            $counts = [];
            foreach ($result as $row) {
                $counts[$row['statut']] = (int) $row['count'];
            }

            return $counts;
        } catch (\Exception $e) {
            $this->logger?->error('Erreur countByStatus', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Retourne les lignes pour un CodeProduit (= ref produit), avec pagination. Renvoie [items, total].
     */
    public function findByCodeProduitPaginated(string $codeProduit, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.CodeProduit = :code')
            ->setParameter('code', trim($codeProduit))
            ->orderBy('s.updatedAt', 'DESC');

        // Exclure LIV si souhaité :
        // $this->excludeLIV($qb, 's');

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

    /**
     * Version filtrable + paginée sur CodeProduit.
     */
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

        // Exclure LIV pour cette vue filtrable (souvent utile)
        $this->excludeLIV($qb, 's');

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

    /* =========================
       Méthodes dédiées "hors LIV"
       ========================= */

    /**
     * Liste DISTINCT des transporteurs (hors LIV), triés A→Z.
     * Retourne un simple tableau de chaînes.
     */
    public function findDistinctTransporteursExcludingLIV(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('DISTINCT s.Transporteur AS transporteur')
            ->where('s.Transporteur IS NOT NULL')
            ->andWhere('TRIM(s.Transporteur) <> \'\'')
            ->orderBy('s.Transporteur', 'ASC');

        $this->excludeLIV($qb, 's');

        return array_map(
            static fn($row) => $row['transporteur'],
            $qb->getQuery()->getArrayResult()
        );
    }

    /**
     * Comptage par transporteur (hors LIV), trié par total décroissant.
     * Retourne un tableau d'array ['transporteur' => string, 'total' => int].
     */
    public function countByTransporteurExcludingLIV(): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('s.Transporteur AS transporteur, COUNT(s.id) AS total')
            ->where('s.Transporteur IS NOT NULL')
            ->andWhere('TRIM(s.Transporteur) <> \'\'')
            ->groupBy('s.Transporteur')
            ->orderBy('total', 'DESC');

        $this->excludeLIV($qb, 's');

        return $qb->getQuery()->getArrayResult();
    }
}
