<?php

namespace App\Repository;

use App\Entity\ImportHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ImportHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportHistory::class);
    }

    public function findLastSuccessfulImport(): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('i')
            ->select('i.importedAt')
            ->where('i.status = :status')
            ->setParameter('status', 'success')
            ->orderBy('i.importedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['importedAt'] : null;
    }

    public function findRecentImports(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.fileName', 'i.importedAt', 'i.status', 'i.recordCount', 'i.errors')
            ->orderBy('i.importedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getImportStats(): array
    {
        return $this->createQueryBuilder('i')
            ->select(
                'COUNT(i.id) as total_imports',
                'SUM(CASE WHEN i.status = :success THEN 1 ELSE 0 END) as successful_imports',
                'SUM(CASE WHEN i.status = :error THEN 1 ELSE 0 END) as failed_imports',
                'SUM(CASE WHEN i.status = :warning THEN 1 ELSE 0 END) as warning_imports',
                'SUM(i.recordCount) as total_records'
            )
            ->setParameters([
                'success' => 'success',
                'error' => 'error',
                'warning' => 'warning'
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findTodayImports(): array
    {
        $today = new \DateTime('today');
        
        return $this->createQueryBuilder('i')
            ->where('i.importedAt >= :today')
            ->setParameter('today', $today)
            ->orderBy('i.importedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function deleteOldImports(int $daysToKeep = 30): int
    {
        $date = new \DateTime("-{$daysToKeep} days");

        return $this->createQueryBuilder('i')
            ->delete()
            ->where('i.importedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    public function findByDateRange(\DateTime $start, \DateTime $end): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.importedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('i.importedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}