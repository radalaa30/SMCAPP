<?php

namespace App\Repository;

use App\Entity\Suividupreparationdujour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Psr\Log\LoggerInterface;

class SuividupreparationdujourRepository extends ServiceEntityRepository
{
    private ?LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger = null)
    {
        parent::__construct($registry, Suividupreparationdujour::class);
        $this->logger = $logger;
    }

    private function excludeLIV(QueryBuilder $qb, string $alias = 's'): void
    {
        $qb->andWhere(sprintf('(%s.Transporteur IS NULL OR UPPER(%s.Transporteur) <> :liv)', $alias, $alias))
           ->setParameter('liv', 'LIV');
    }

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

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findByNoBLWithSpecificAddresses', [
                'numBl' => $numBl,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function findBySpecificAddressPattern()
    {
        try {
            $qb = $this->createQueryBuilder('s');
            $qb->where($qb->expr()->orX(
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

            $this->excludeLIV($qb, 's');
            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findBySpecificAddressPattern', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function findByCodeProduit(string $codeProduit)
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.CodeProduit = :codeProduit')
                ->setParameter('codeProduit', $codeProduit);

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findByCodeProduit', [
                'codeProduit' => $codeProduit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function findPending()
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.Statut_Cde = :statut')
                ->setParameter('statut', 'En attente')
                ->orderBy('s.No_Bl', 'ASC');

            $this->excludeLIV($qb, 's');
            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findPending', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function findByDate(\DateTimeInterface $date)
    {
        try {
            $dateString = $date->format('Y-m-d');
            $qb = $this->createQueryBuilder('s')
                ->where('DATE(s.Date_liv) = :date')
                ->setParameter('date', $dateString)
                ->orderBy('s.No_Bl', 'ASC');

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

    public function findByCreatedDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate)
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.created BETWEEN :startDate AND :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->orderBy('s.created', 'DESC');

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

    public function findValidated()
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.valider IS NOT NULL')
                ->orderBy('s.valider', 'DESC');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findValidated', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function findNotValidated()
    {
        try {
            $qb = $this->createQueryBuilder('s')
                ->where('s.valider IS NULL')
                ->orderBy('s.created', 'DESC');

            return $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            $this->logger?->error('Erreur findNotValidated', ['error' => $e->getMessage()]);
            return [];
        }
    }

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
            $this->logger?->error('Erreur countByStatus', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function findByCodeProduitPaginated(string $codeProduit, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.CodeProduit = :code')
            ->setParameter('code', trim($codeProduit))
            ->orderBy('s.updatedAt', 'DESC');

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(s.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

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

        if (!empty($f['noBl']))         { $qb->andWhere('s.No_Bl LIKE :noBl')->setParameter('noBl', '%'.$f['noBl'].'%'); }
        if (!empty($f['noCmd']))        { $qb->andWhere('s.No_Cmd LIKE :noCmd')->setParameter('noCmd', '%'.$f['noCmd'].'%'); }
        if (!empty($f['client']))       { $qb->andWhere('s.Client LIKE :client')->setParameter('client', '%'.$f['client'].'%'); }
        if (!empty($f['codeClient']))   { $qb->andWhere('s.Code_Client LIKE :codeClient')->setParameter('codeClient', '%'.$f['codeClient'].'%'); }
        if (!empty($f['zone']))         { $qb->andWhere('s.Zone LIKE :zone')->setParameter('zone', '%'.$f['zone'].'%'); }
        if (!empty($f['adresse']))      { $qb->andWhere('s.Adresse LIKE :adresse')->setParameter('adresse', '%'.$f['adresse'].'%'); }
        if (!empty($f['flasher']))      { $qb->andWhere('s.Flasher LIKE :flasher')->setParameter('flasher', '%'.$f['flasher'].'%'); }
        if (!empty($f['preparateur']))  { $qb->andWhere('s.Preparateur LIKE :preparateur')->setParameter('preparateur', '%'.$f['preparateur'].'%'); }
        if (!empty($f['transporteur'])) { $qb->andWhere('s.Transporteur LIKE :transporteur')->setParameter('transporteur', '%'.$f['transporteur'].'%'); }

        if (!empty($f['maj_from'])) { $qb->andWhere('s.updatedAt >= :majFrom')->setParameter('majFrom', $f['maj_from']); }
        if (!empty($f['maj_to']))   { $qb->andWhere('s.updatedAt <= :majTo')->setParameter('majTo',   $f['maj_to']); }
        if (!empty($f['liv_from'])) { $qb->andWhere('s.Date_liv >= :livFrom')->setParameter('livFrom', $f['liv_from']); }
        if (!empty($f['liv_to']))   { $qb->andWhere('s.Date_liv <= :livTo')->setParameter('livTo',   $f['liv_to']); }

        $this->excludeLIV($qb, 's');

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(s.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $qb->orderBy('s.updatedAt', 'DESC')->addOrderBy('s.Date_liv', 'DESC')
           ->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();
        return [$items, $total];
    }

    /** KO = Flasher = 'KO' + filtres optionnels */
    public function createKoQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.Flasher = :status')
            ->setParameter('status', 'KO')
            ->orderBy('s.updatedAt', 'DESC');

        if (!empty($filters['codeProduit'])) {
            $qb->andWhere('s.CodeProduit LIKE :codeProduit')->setParameter('codeProduit', '%'.$filters['codeProduit'].'%');
        }
        if (!empty($filters['client'])) {
            $qb->andWhere('s.Client LIKE :client')->setParameter('client', '%'.$filters['client'].'%');
        }
        if (!empty($filters['preparateur'])) {
            $qb->andWhere('s.Preparateur LIKE :prep')->setParameter('prep', '%'.$filters['preparateur'].'%');
        }
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('s.Date_liv >= :dateFrom')->setParameter('dateFrom', $filters['dateFrom']->format('Y-m-d 00:00:00'));
        }
        if (!empty($filters['dateTo'])) {
            $qb->andWhere('s.Date_liv <= :dateTo')->setParameter('dateTo', $filters['dateTo']->format('Y-m-d 23:59:59'));
        }

        return $qb;
    }

    /** @return array{items: array<Suividupreparationdujour>, total:int} */
    public function paginateKo(array $filters = [], int $page = 1, int $limit = 25): array
    {
        $page = max(1, $page);
        $limit = max(1, min($limit, 200));

        $qb = $this->createKoQueryBuilder($filters)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        $countQb = clone $this->createKoQueryBuilder($filters);
        $countQb->select('COUNT(s.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Pagination KO + dernier suivi (map [idSuivi => KoSuivi|null])
     * @return array{items: array<Suividupreparationdujour>, total:int, last: array<int, \App\Entity\KoSuivi|null>}
     */
    public function paginateKoWithLast(array $filters = [], int $page = 1, int $limit = 25, ?KoSuiviRepository $koRepo = null): array
    {
        $base = $this->paginateKo($filters, $page, $limit);
        $items = $base['items'];

        $ids = array_map(static fn(Suividupreparationdujour $s) => $s->getId(), $items);
        $last = [];

        if ($ids) {
            /** @var KoSuiviRepository $koRepo */
            $koRepo ??= $this->getEntityManager()->getRepository(\App\Entity\KoSuivi::class);
            $last = $koRepo->findLatestForIds($ids);
        }

        return ['items' => $items, 'total' => $base['total'], 'last' => $last];
    }

    /**
     * Export KO + dernier suivi (limité à 10k par défaut)
     * @return array{items: array<Suividupreparationdujour>, last: array<int, \App\Entity\KoSuivi|null>}
     */
    public function exportKoWithLast(array $filters = [], int $limit = 10000, ?KoSuiviRepository $koRepo = null): array
    {
        $qb = $this->createKoQueryBuilder($filters)->setMaxResults($limit);
        $items = $qb->getQuery()->getResult();

        $ids = array_map(static fn(Suividupreparationdujour $s) => $s->getId(), $items);
        $last = [];

        if ($ids) {
            /** @var KoSuiviRepository $koRepo */
            $koRepo ??= $this->getEntityManager()->getRepository(\App\Entity\KoSuivi::class);
            $last = $koRepo->findLatestForIds($ids);
        }

        return ['items' => $items, 'last' => $last];
    }
}
