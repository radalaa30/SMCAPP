<?php

namespace App\Repository;

use App\Entity\Suividupreparationdujour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;

class SuividupreparationdujourSearchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Suividupreparationdujour::class);
    }

    public function findBySearchCriteria(array $criteria)
    {
        $qb = $this->createQueryBuilder('s');

        if (isset($criteria['suivi_preparation_search']) && is_array($criteria['suivi_preparation_search'])) {
            $searchCriteria = $criteria['suivi_preparation_search'];
         
            // Récupération des valeurs
            $codeClient = $searchCriteria['codeClient'] ?? null;
            $client = $searchCriteria['client'] ?? null;
            $preparateur = $searchCriteria['preparateur'] ?? null;
            $updatedAt = $searchCriteria['updatedAt'] ?? null;
            $nbArt = $searchCriteria['nbArt'] ?? null; // Correction: renommé nb_art -> nbArt pour être cohérent avec le formulaire
            $noBl = $searchCriteria['noBl'] ?? null;
            $noCmd = $searchCriteria['noCmd'] ?? null;
            $codeProduit = $searchCriteria['codeProduit'] ?? null;   
            $dateLiv = $searchCriteria['dateliv'] ?? null;

            // Ajout de filtres incrementaux selon les critères fournis
            if (!empty($codeClient)) {
                $qb->andWhere('s.Code_Client LIKE :codeClient')
                   ->setParameter('codeClient', '%' . $codeClient . '%');
            }

            if (!empty($client)) {
                $qb->andWhere('s.Client LIKE :client')
                   ->setParameter('client', '%' . $client . '%');
            }

            if (!empty($preparateur)) {
                $qb->andWhere('s.Preparateur LIKE :preparateur')
                   ->setParameter('preparateur', '%' . $preparateur . '%');
            }

            // Correction: filtre nbArt utilisant le champ correct
            if (!empty($nbArt)) {
                $qb->andWhere('s.nbArt = :nbArt')
                   ->setParameter('nbArt', $nbArt);
            }

            if (!empty($updatedAt)) {
                $date = \DateTime::createFromFormat('Y-m-d', $updatedAt);
            
                if ($date) {
                    $startOfDay = clone $date;
                    $startOfDay->setTime(0, 0, 0);
            
                    $endOfDay = clone $date;
                    $endOfDay->setTime(23, 59, 59);
            
                    $qb->andWhere('s.updatedAt BETWEEN :startOfDay AND :endOfDay')
                       ->setParameter('startOfDay', $startOfDay)
                       ->setParameter('endOfDay', $endOfDay);
                }
            }
            
            if (!empty($dateLiv)) {
                $date = \DateTime::createFromFormat('Y-m-d', $dateLiv, new \DateTimeZone('Europe/Paris'));
            
                if ($date instanceof \DateTime) {
                    $startOfDay = (clone $date)->setTime(0, 0, 0);
                    $endOfDay = (clone $date)->setTime(23, 59, 59);
            
                    $qb->andWhere('s.Date_liv BETWEEN :startDayLiv AND :endDayLiv')
                       ->setParameter('startDayLiv', $startOfDay->format('Y-m-d H:i:s'))
                       ->setParameter('endDayLiv', $endOfDay->format('Y-m-d H:i:s'));
                }
            }
            
            if (!empty($noBl)) {
                $qb->andWhere('s.No_Bl LIKE :noBl')
                   ->setParameter('noBl', '%' . $noBl . '%');
            }

            if (!empty($noCmd)) {
                // Extraire les 8 derniers caractères
                $noCmd = substr($noCmd, -8); 

                $qb->andWhere('s.No_Cmd LIKE :noCmd')
                   ->setParameter('noCmd', '%' . $noCmd);
            }

            if (!empty($codeProduit)) {
                $codesProduit = array_map('trim', explode(',', $codeProduit));
                $codesProduit = array_filter($codesProduit);
                
                if (!empty($codesProduit)) {
                    // Version simplifiée et optimisée pour éviter les problèmes de mémoire
                    if (count($codesProduit) === 1) {
                        // Si un seul code produit, requête simple
                        $qb->andWhere('s.CodeProduit = :codeProduit')
                           ->setParameter('codeProduit', $codesProduit[0]);
                    } else {
                        // Si plusieurs codes, utiliser une jointure au lieu d'une sous-requête
                        $qb->andWhere('s.CodeProduit IN (:codesProduit)')
                           ->setParameter('codesProduit', $codesProduit);
                        
                        // Ajouter une annotation pour limiter les résultats
                        $qb->setMaxResults(1000); // Limite raisonnable pour éviter épuisement mémoire
                    }
                }
            }
        }

        // Optimisation importante: retourne la Query, pas le Result
        return $qb->orderBy('s.updatedAt', 'DESC')
                 ->getQuery();
    }
}