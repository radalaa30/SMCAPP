<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Persiste/flush un utilisateur.
     */
    public function save(User $user, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($user);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Supprime/flush un utilisateur.
     */
    public function remove(User $user, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->remove($user);
        if ($flush) {
            $em->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user, true);
    }

    /**
     * Pagination + recherche simple (username/email).
     *
     * @return array{0: User[], 1: int} [items, total]
     */
    public function findPaginated(int $page = 1, int $limit = 20, ?string $search = null): array
    {
        $page  = max(1, $page);
        $limit = max(1, $limit);

        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.username', 'ASC');

        if ($search) {
            $qb->andWhere('u.username LIKE :q OR u.email LIKE :q')
               ->setParameter('q', '%' . trim($search) . '%');
        }

        // total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')
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
     * Trouver par username ou email.
     */
    public function findOneByUsernameOrEmail(string $term): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.username = :term OR u.email = :term')
            ->setParameter('term', $term)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
