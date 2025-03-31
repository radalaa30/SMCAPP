<?php

namespace App\Repository;

use App\Entity\Suividupreparationdujour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
            $nbArt = $searchCriteria['nb_art'] ?? null;
            $noBl = $searchCriteria['noBl'] ?? null;
            $noCmd = $searchCriteria['noCmd'] ?? null;
            $codeProduit = $searchCriteria['codeProduit'] ?? null;   
            $dateLiv = $searchCriteria['dateliv'] ?? null;

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

            if (!empty($nbArt)) {
                $qb->andWhere('s.Preparateur LIKE :preparateur')
                   ->setParameter('preparateur', '%' . $preparateur . '%');
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
                    $endOfDay = (clone $date)->modify('23:59:59');
            
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

                // Requête avec LIKE
                $qb->andWhere('s.No_Cmd LIKE :noCmd')
                ->setParameter('noCmd', '%' . $noCmd);
            }

            if (!empty($codeProduit)) {
                $codesProduit = array_map('trim', explode(',', $codeProduit));
                $codesProduit = array_filter($codesProduit);
                
                if (!empty($codesProduit)) {
                    // Sous-requête pour trouver les BL qui contiennent tous les produits
                    $subQb = $this->createQueryBuilder('sub')
                        ->select('DISTINCT sub.No_Bl')
                        ->andWhere('sub.CodeProduit IN (:codes)')
                        ->setParameter('codes', $codesProduit)
                        ->groupBy('sub.No_Bl')
                        ->having('COUNT(DISTINCT sub.CodeProduit) >= :productCount')
                        ->setParameter('productCount', count($codesProduit));
            
                    // Requête principale : BL trouvés ET seulement les produits recherchés
                    $qb->andWhere('s.No_Bl IN (' . $subQb->getDQL() . ')')
                       ->andWhere('s.CodeProduit IN (:codes_main)') // Changé le nom du paramètre
                       ->setParameter('codes_main', $codesProduit)
                       ->setParameter('codes', $codesProduit)
                       ->setParameter('productCount', count($codesProduit));
                }
            }
        }

        return $qb->orderBy('s.updatedAt', 'DESC')
                 ->getQuery()
                 ->getResult();
    }
}