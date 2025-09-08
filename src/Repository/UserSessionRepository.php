<?php


namespace App\Repository;


use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


/**
* @extends ServiceEntityRepository<UserSession>
*/
class UserSessionRepository extends ServiceEntityRepository
{
public function __construct(ManagerRegistry $registry)
{
parent::__construct($registry, UserSession::class);
}


/** @return UserSession[] */
public function findActiveByUser(User $user): array
{
return $this->createQueryBuilder('s')
->andWhere('s.user = :user')
->andWhere('s.revokedAt IS NULL')
->andWhere('s.expiresAt > :now')
->setParameter('user', $user)
->setParameter('now', new \DateTimeImmutable())
->orderBy('s.createdAt', 'DESC')
->getQuery()->getResult();
}


public function findOneBySessionId(string $sessionId): ?UserSession
{
return $this->findOneBy(['sessionId' => $sessionId]);
}


public function revokeAllOtherSessions(User $user, string $keepSessionId): void
{
$em = $this->getEntityManager();
$qb = $this->createQueryBuilder('s')
->update()
->set('s.revokedAt', ':now')
->where('s.user = :user')
->andWhere('s.sessionId != :sid')
->andWhere('s.revokedAt IS NULL')
->setParameter('now', new \DateTimeImmutable())
->setParameter('user', $user)
->setParameter('sid', $keepSessionId)
;
$qb->getQuery()->execute();
$em->flush();
}


public function purgeExpired(int $olderThanDays = 30): int
{
$threshold = (new \DateTimeImmutable())->modify('-'.$olderThanDays.' days');
$em = $this->getEntityManager();
$conn = $em->getConnection();
$sql = 'DELETE FROM user_sessions WHERE (expires_at < NOW() OR revoked_at IS NOT NULL) AND created_at < :threshold';
return $conn->executeStatement($sql, ['threshold' => $threshold->format('Y-m-d H:i:s')]);
}
}