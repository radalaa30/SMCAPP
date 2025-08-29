<?php

namespace App\Repository;

use App\Entity\SuividupreparationdujourRea;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<SuividupreparationdujourRea>
 */
class SuividupreparationdujourReaRepository extends ServiceEntityRepository
{
    private ?LoggerInterface $logger;

    public function __construct(ManagerRegistry $registry, ?LoggerInterface $logger = null)
    {
        parent::__construct($registry, SuividupreparationdujourRea::class);
        $this->logger = $logger;
    }

    /**
     * BL exact + flasher vide (NULL ou "") + adresse finissant par -01/-02/-03/-04
     * et ne contenant pas 'ETAG'
     *
     * @return SuividupreparationdujourRea[]
     */
    public function findByNoBLWithSpecificAddresses(string $numBl): array
    {
        try {
            $qb = $this->createQueryBuilder('s');

            return $qb
                ->where('s.noBl = :numBl')
                ->andWhere($qb->expr()->orX('s.flasher IS NULL', 's.flasher = :empty'))
                ->andWhere('s.adresse NOT LIKE :etag')
                ->andWhere($qb->expr()->orX(
                    's.adresse LIKE :s1',
                    's.adresse LIKE :s2',
                    's.adresse LIKE :s3',
                    's.adresse LIKE :s4'
                ))
                ->setParameter('numBl', $numBl)
                ->setParameter('empty', '')
                ->setParameter('etag', '%ETAG%')
                ->setParameter('s1', '%-01')
                ->setParameter('s2', '%-02')
                ->setParameter('s3', '%-03')
                ->setParameter('s4', '%-04')
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['numBl' => $numBl, 'e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Adresses finissant par -01/-02/-03/-04 et exclure celles où "Zone:Adresse" commence par 'C2S:G'
     *
     * @return SuividupreparationdujourRea[]
     */
    public function findBySpecificAddressPattern(): array
    {
        try {
            $qb = $this->createQueryBuilder('s');

            return $qb
                ->where($qb->expr()->orX(
                    's.adresse LIKE :p1',
                    's.adresse LIKE :p2',
                    's.adresse LIKE :p3',
                    's.adresse LIKE :p4'
                ))
                ->andWhere("CONCAT(s.zone, ':', s.adresse) NOT LIKE :exclude")
                ->setParameter('p1', '%-01')
                ->setParameter('p2', '%-02')
                ->setParameter('p3', '%-03')
                ->setParameter('p4', '%-04')
                ->setParameter('exclude', 'C2S:G%')
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * @return SuividupreparationdujourRea[]
     */
    public function findByCodeProduit(string $codeProduit): array
    {
        try {
            return $this->createQueryBuilder('s')
                ->where('s.codeProduit = :cp')
                ->setParameter('cp', $codeProduit)
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['codeProduit' => $codeProduit, 'e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Statut en attente
     *
     * @return SuividupreparationdujourRea[]
     */
    public function findPending(): array
    {
        try {
            return $this->createQueryBuilder('s')
                ->where('s.statutCde = :statut')
                ->setParameter('statut', 'En attente')
                ->orderBy('s.noBl', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Par date de livraison (journée civile)
     *
     * @return SuividupreparationdujourRea[]
     */
    public function findByDate(\DateTimeInterface $date): array
    {
        try {
            $start = (new \DateTimeImmutable($date->format('Y-m-d')))->setTime(0, 0, 0);
            $end   = $start->modify('+1 day');

            return $this->createQueryBuilder('s')
                ->andWhere('s.dateLiv >= :start')
                ->andWhere('s.dateLiv < :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->orderBy('s.noBl', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['date' => $date->format('Y-m-d'), 'e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Intervalle sur updatedAt (l’entity n’a pas de createdAt)
     *
     * @return SuividupreparationdujourRea[]
     */
    public function findByUpdatedDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        try {
            return $this->createQueryBuilder('s')
                ->andWhere('s.updatedAt >= :start')
                ->andWhere('s.updatedAt <= :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->orderBy('s.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', [
                'start' => $startDate->format(DATE_ATOM),
                'end'   => $endDate->format(DATE_ATOM),
                'e'     => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Validés (valider non NULL)
     *
     * @return SuividupreparationdujourRea[]
     */
    public function findValidated(): array
    {
        try {
            return $this->createQueryBuilder('s')
                ->andWhere('s.valider IS NOT NULL')
                ->orderBy('s.valider', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Non validés (valider NULL)
     *
     * @return SuividupreparationdujourRea[]
     */
    public function findNotValidated(): array
    {
        try {
            return $this->createQueryBuilder('s')
                ->andWhere('s.valider IS NULL')
                ->orderBy('s.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Par transporteur
     *
     * @return SuividupreparationdujourRea[]
     */
    public function findByTransporteur(string $transporteur): array
    {
        try {
            return $this->createQueryBuilder('s')
                ->andWhere('s.transporteur = :t')
                ->setParameter('t', $transporteur)
                ->orderBy('s.dateLiv', 'ASC')
                ->getQuery()
                ->getResult();
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['transporteur' => $transporteur, 'e' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Comptage par statut
     *
     * @return array<string,int>
     */
    public function countByStatus(): array
    {
        try {
            $rows = $this->createQueryBuilder('s')
                ->select('s.statutCde AS statut, COUNT(s.id) AS cnt')
                ->groupBy('s.statutCde')
                ->getQuery()
                ->getResult();

            $out = [];
            foreach ($rows as $r) {
                $out[$r['statut'] ?? ''] = (int) $r['cnt'];
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logger?->error(__METHOD__.' error', ['e' => $e->getMessage()]);
            return [];
        }
    }
}
