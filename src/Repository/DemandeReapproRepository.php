<?php

namespace App\Repository;

use App\Entity\DemandeReappro;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeReappro>
 */
class DemandeReapproRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeReappro::class);
    }
    public function     findOldestByStatusExcludingVAndE(int $idCariste)
    {
  
    $entityManager = $this->getEntityManager();

    // Créer le QueryBuilder et définir la requête
    $query = $this->createQueryBuilder('e')
        ->where('e.Statut != :valide')         // Filtrer où le statut n'est pas 'V'
        ->andWhere('e.Statut != :encour')      // et le statut n'est pas 'E'
        ->andWhere('e.idCariste = :idCariste')// et id_cariste est égal à 62
        ->andWhere('e.UsernamePrep != :coldUsername')
        ->setParameter('valide', 'V')          // Définir le paramètre 'valide' avec la valeur 'V'
        ->setParameter('encour', 'Encours')          // Définir le paramètre 'encour' avec la valeur 'E'
        ->setParameter('idCariste', $idCariste)        // Définir le paramètre 'idCariste' avec la valeur 62
        ->setParameter('coldUsername', 'Cold')
        ->orderBy('e.CreateAt', 'ASC')         // Trier par date de création en ordre croissant
        ->setMaxResults(1)                     // Limiter les résultats à 1
        ->getQuery();                          // Obtenir la requête

    // Exécuter la requête et obtenir le résultat unique
    try {
        $entity = $query->getSingleResult();
    } catch (\Doctrine\ORM\NoResultException $e) {
        return null; // Aucun résultat trouvé
    }

    // Modifier le statut de l'entité
    $entity->setStatut('Encours');
    

    // Sauvegarder les changements dans la base de données
    $entityManager->persist($entity);
    $entityManager->flush();

    return $entity;

    

    }

    public function findOldestByStatusExcludingVAndEBYIDcariste(int $idCariste)
        {
            $entityManager = $this->getEntityManager();

            // Créer le QueryBuilder et définir la requête
            $query = $this->createQueryBuilder('e')
                ->where('e.idCariste = :idCariste') // Filtrer où id_cariste est égal au paramètre idCariste
                ->andWhere('e.Statut = :encours')   // et le statut est 'Encours'
                ->andWhere('e.UsernamePrep != :coldUsername')
                ->setParameter('idCariste', $idCariste) // Définir le paramètre 'idCariste'
                ->setParameter('encours', 'Encours')    // Définir le paramètre 'encours' avec la valeur 'Encours'
                ->setParameter('coldUsername', 'Cold')
                ->orderBy('e.CreateAt', 'ASC')     // Trier par date de création en ordre croissant
                ->setMaxResults(1)                 // Limiter les résultats à 1
                ->getQuery();                      // Obtenir la requête

            try {
                // Exécuter la requête et obtenir le résultat unique
                $entity = $query->getSingleResult();
            } catch (\Doctrine\ORM\NoResultException $e) {
                return null; // Aucun résultat trouvé
            }

            return $entity;
        }
    
        public function findOldestByStatusExcludingVAndEBYIDcaristecold(int $idCariste)
        {
            $entityManager = $this->getEntityManager();

            // Créer le QueryBuilder et définir la requête
            $query = $this->createQueryBuilder('e')
                ->where('e.idCariste = :idCariste') // Filtrer où id_cariste est égal au paramètre idCariste
                ->andWhere('e.Statut = :encours')   // et le statut est 'Encours'
                ->andWhere('e.UsernamePrep = :coldUsername')
                ->setParameter('idCariste', $idCariste) // Définir le paramètre 'idCariste'
                ->setParameter('encours', 'Encours')    // Définir le paramètre 'encours' avec la valeur 'Encours'
                ->setParameter('coldUsername', 'Cold')
                ->orderBy('e.CreateAt', 'ASC')     // Trier par date de création en ordre croissant
                ->setMaxResults(1)                 // Limiter les résultats à 1
                ->getQuery();                      // Obtenir la requête

            try {
                // Exécuter la requête et obtenir le résultat unique
                $entity = $query->getSingleResult();
            } catch (\Doctrine\ORM\NoResultException $e) {
                return null; // Aucun résultat trouvé
            }

            return $entity;
        }

public function findOldestByStatusA(int $idCariste,string $userusername )
{
    $entityManager = $this->getEntityManager();

    // Créer le QueryBuilder et définir la requête
    $query = $this->createQueryBuilder('e')
        ->where('e.Statut = :statutA')   // Filtrer où le statut est 'A'
        ->andWhere('e.UsernamePrep != :coldUsername')
        ->setParameter('statutA', 'A')   // Définir le paramètre 'statutA' avec la valeur 'A'
        ->setParameter('coldUsername', 'Cold')
        ->orderBy('e.CreateAt', 'ASC')   // Trier par date de création en ordre croissant
        ->setMaxResults(1)               // Limiter les résultats à 1
        ->getQuery();                    // Obtenir la requête

    try {
        // Exécuter la requête et obtenir le résultat unique
        $entity = $query->getSingleResult();
    } catch (\Doctrine\ORM\NoResultException $e) {
        return null; // Aucun résultat trouvé
    }

    // Modifier le statut de l'entité
    $entity->setStatut('Encours');
    // Modifier le statut de l'entité
    $entity->setidCariste($idCariste);
  
    $entity->setUsernameCariste($userusername);
    


    // Sauvegarder les changements dans la base de données
    $entityManager->persist($entity);
    $entityManager->flush();

    return $entity;
}
public function findOldestByStatusAcold(int $idCariste, string $userusername)
{
    $entityManager = $this->getEntityManager();

    // Créer le QueryBuilder et définir la requête avec priorisation par adresse
    $query = $this->createQueryBuilder('e')
        ->where('e.Statut = :statutA')   // Filtrer où le statut est 'A'
        ->andWhere('e.UsernamePrep = :coldUsername')
        ->setParameter('statutA', 'A')   // Définir le paramètre 'statutA' avec la valeur 'A'
        ->setParameter('coldUsername', 'Cold')
        ->addOrderBy('CASE 
            WHEN e.Adresse LIKE :c4d THEN 1
            WHEN e.Adresse LIKE :c4c THEN 2
            WHEN e.Adresse LIKE :c4b THEN 3
            WHEN e.Adresse LIKE :c4a THEN 4
            WHEN e.Adresse LIKE :c3h THEN 5
            WHEN e.Adresse LIKE :c3g THEN 6
            WHEN e.Adresse LIKE :c3f THEN 7
            WHEN e.Adresse LIKE :c3e THEN 8
            WHEN e.Adresse LIKE :c3d THEN 9
            WHEN e.Adresse LIKE :c3c THEN 10
            WHEN e.Adresse LIKE :c3b THEN 11
            WHEN e.Adresse LIKE :c3a THEN 12
            WHEN e.Adresse LIKE :c2h THEN 13
            WHEN e.Adresse LIKE :c2g THEN 14
            WHEN e.Adresse LIKE :c2f THEN 15
            WHEN e.Adresse LIKE :c2e THEN 16
            WHEN e.Adresse LIKE :c2d THEN 17
            WHEN e.Adresse LIKE :c2c THEN 18
            WHEN e.Adresse LIKE :c2b THEN 19
            WHEN e.Adresse LIKE :c2a THEN 20
            ELSE 999
        END', 'ASC')
        ->addOrderBy('e.CreateAt', 'ASC')   // Ensuite trier par date de création en ordre croissant
        ->setParameter('c4d', 'C4:D%')
        ->setParameter('c4c', 'C4:C%')
        ->setParameter('c4b', 'C4:B%')
        ->setParameter('c4a', 'C4:A%')
        ->setParameter('c3h', 'C3:H%')
        ->setParameter('c3g', 'C3:G%')
        ->setParameter('c3f', 'C3:F%')
        ->setParameter('c3e', 'C3:E%')
        ->setParameter('c3d', 'C3:D%')
        ->setParameter('c3c', 'C3:C%')
        ->setParameter('c3b', 'C3:B%')
        ->setParameter('c3a', 'C3:A%')
        ->setParameter('c2h', 'C2:H%')
        ->setParameter('c2g', 'C2:G%')
        ->setParameter('c2f', 'C2:F%')
        ->setParameter('c2e', 'C2:E%')
        ->setParameter('c2d', 'C2:D%')
        ->setParameter('c2c', 'C2:C%')
        ->setParameter('c2b', 'C2:B%')
        ->setParameter('c2a', 'C2:A%')
        ->setMaxResults(1)               // Limiter les résultats à 1
        ->getQuery();                    // Obtenir la requête

    try {
        // Exécuter la requête et obtenir le résultat unique
        $entity = $query->getSingleResult();
    } catch (\Doctrine\ORM\NoResultException $e) {
        return null; // Aucun résultat trouvé
    }

    // Modifier le statut de l'entité
    $entity->setStatut('Encours');
    // Modifier le statut de l'entité
    $entity->setidCariste($idCariste);
    
    $entity->setUsernameCariste($userusername);

    // Sauvegarder les changements dans la base de données
    $entityManager->persist($entity);
    $entityManager->flush();

    return $entity;
}

    /**
     * Recherche les demandes de réappro selon les filtres fournis
     * @param array $filters Tableau des filtres à appliquer
     * @return DemandeReappro[]
     */
    public function findByFilters(array $filters): array
    {
  
        //page admin commance ici
        $qb = $this->createQueryBuilder('d');

        // Filtre par statut
        if (isset($filters['Statut'])) {
            $qb->andWhere('d.Statut = :statut')
               ->setParameter('statut', $filters['Statut']);
        }

        // Filtre par ID préparateur
        if (isset($filters['idPreparateur'])) {
            $qb->andWhere('d.idPreparateur = :idPreparateur')
               ->setParameter('idPreparateur', $filters['idPreparateur']);
        }

         // Filtre par ID préparateur
         if (isset($filters['username_prep'])) {
            $qb->andWhere('d.username_prep = :username_prep')
            ->setParameter('username_prep', $filters['username_prep']);
        }
        

        // Filtre par ID préparateur
        if (isset($filters['idPreparateur'])) {
            $qb->andWhere('d.idPreparateur = :idPreparateur')
               ->setParameter('idPreparateur', $filters['idPreparateur']);
        }

        // Filtre par ID cariste
        if (isset($filters['idCariste'])) {
            $qb->andWhere('d.idCariste = :idCariste')
               ->setParameter('idCariste', $filters['idCariste']);
        }

        // Filtre par adresse
        if (isset($filters['Adresse'])) {
            $qb->andWhere('d.Adresse LIKE :adresse')
               ->setParameter('adresse', '%' . $filters['Adresse'] . '%');
        }

        // Filtre par plage de dates de création
        if (isset($filters['dateDebut'])) {
            $qb->andWhere('d.CreateAt >= :dateDebut')
               ->setParameter('dateDebut', new \DateTimeImmutable($filters['dateDebut']));
        }

        if (isset($filters['dateFin'])) {
            $qb->andWhere('d.CreateAt <= :dateFin')
               ->setParameter('dateFin', new \DateTimeImmutable($filters['dateFin']));
        }

        // Filtre par Son Picking
        if (isset($filters['SonPicking'])) {
            $qb->andWhere('d.SonPicking = :sonPicking')
               ->setParameter('sonPicking', $filters['SonPicking']);
        }

        // Tri par date de création décroissante par défaut
        $qb->orderBy('d.CreateAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère la liste des caristes avec leurs usernames
     */
    public function findAllCaristesWithUsernames(): array
    {
        return $this->createQueryBuilder('d')
            ->select('DISTINCT d.idCariste, u.username')
            ->join('App\Entity\User', 'u', 'WITH', 'd.idCariste = u.id')
            ->where('d.idCariste IS NOT NULL')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

     /**
     * Récupère la liste des préparateurs avec leurs usernames
     */
    public function findAllPreparateursWithUsernames(): array
    {
        return $this->createQueryBuilder('d')
            ->select('DISTINCT d.idPreparateur, u.username')
            ->join('App\Entity\User', 'u', 'WITH', 'd.idPreparateur = u.id')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si une demande existe déjà pour une adresse donnée
     * avec le statut 'A' ou 'Encours'
     */
    public function findExistingDemandeByAdresse(string $adresse): ?DemandeReappro
    {
        
        return $this->createQueryBuilder('d')
            ->where('d.Adresse = :adresse')
            ->andWhere('d.Statut IN (:statuts)')
            ->setParameter('adresse', $adresse)
            ->setParameter('statuts', ['A', 'Encours'])
            ->getQuery()
            ->getOneOrNullResult();
    }

   
}
