<?php

namespace App\Controller;

use App\Repository\SuividupreparationdujourRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\Query\ResultSetMapping;

#[Route('/admin')]
class SuividupreparationdujourController extends AbstractController
{
    private const STATUS_OK = 'Ok';
    private const AGENCY_PREFIX = 'CI999%';
    private const CLIENT_PREFIX = 'C0%';
  
    #[Route('/suivi/stats', name: 'app_suivi_stats')]
    public function stats(SuividupreparationdujourRepository $repository): Response
    {
        $startOfDay = new \DateTime('today');
        $endOfDay = new \DateTime('today 23:59:59');

        $lastUpdatedAt = $repository->createQueryBuilder('s')
            ->select('MAX(s.updatedAt) AS lastUpdatedAt')
            ->where('s.updatedAt BETWEEN :startOfDay AND :endOfDay')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        $statsGlobales = $this->getStatsGlobales($repository, $startOfDay, $endOfDay);
        $statsAgence = $this->getStatsParAgence($repository, $startOfDay, $endOfDay);
        $statsPreparateur = $this->getStatsParPreparateur($repository, $startOfDay, $endOfDay);

        return $this->render('suividupreparationdujour/stats.html.twig', [
            'statsStatut' => $this->getStatsParEtat($repository, $startOfDay, $endOfDay),
            'statsPreparateur' => $statsPreparateur,
            'statsGlobales' => $statsGlobales,
            'statsAgence' => $statsAgence,
            'statsClient' => $this->getStatsParClient($repository, $startOfDay, $endOfDay),
            'dataPreparateurs' => $this->prepareDataPreparateurs($statsPreparateur),
            'dataAgences' => $this->prepareDataAgences($statsAgence),
            'lastUpdatedAt' => $lastUpdatedAt,
            'totalPreparationsClient' => $statsGlobales['total_preparations_client'] ?? 0,
            'preparationsClientEnCours' => $statsGlobales['preparations_client_en_cours'] ?? 0,
            'totalPreparationsAgence' => $statsGlobales['total_preparations_agence'] ?? 0,
            'preparationsAgenceEnCours' => $statsGlobales['preparations_agence_en_cours'] ?? 0,
        ]);
    }

    private function executeStatsQuery(
        SuividupreparationdujourRepository $repository,
        array $groupBy = [],
        ?string $whereClause = null,
        string|array|null $parameters = null,
        \DateTime $startOfDay = null,
        \DateTime $endOfDay = null
    ): array {
        $selectFields = [];

        foreach ($groupBy as $field) {
            $fieldName = substr($field, strpos($field, '.') + 1);
            $selectFields[] = "s.$fieldName";
        }

        $selectFields = array_merge($selectFields, [
            'COUNT(s.id) as total_preparations',
            'SUM(CASE WHEN s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees',
            'SUM(CASE WHEN s.Flasher IS NULL OR s.Flasher = \'\' THEN 1 ELSE 0 END) as preparations_en_cours',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees_client',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\') THEN 1 ELSE 0 END) as preparations_en_cours_client',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix AND s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees_agence',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\') THEN 1 ELSE 0 END) as preparations_en_cours_agence',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix THEN 1 ELSE 0 END) as total_preparations_client',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\') THEN 1 ELSE 0 END) as preparations_client_en_cours',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix THEN 1 ELSE 0 END) as total_preparations_agence',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\') THEN 1 ELSE 0 END) as preparations_agence_en_cours',
            'SUM(s.Nb_Pal) as total_palettes',
            'SUM(s.Nb_col) as total_colis',
            'CAST(
                CASE 
                    WHEN COUNT(s.id) > 0 THEN 
                        (SUM(CASE WHEN s.Flasher = :status_ok THEN 1 ELSE 0 END) * 100.0) / COUNT(s.id)
                    ELSE 0 
                END 
            AS DECIMAL(10,2)) as pourcentage_avancement',
            'GROUP_CONCAT(DISTINCT CASE WHEN s.Flasher IS NULL OR s.Flasher = \'\' THEN s.Preparateur END SEPARATOR \', \') as preparateurs'
        ]);

        $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM suividupreparationdujour s';

        $dateCondition = ' s.updatedAt BETWEEN :startOfDay AND :endOfDay';
        $whereClause = $whereClause 
            ? $whereClause . ' AND ' . $dateCondition 
            : $dateCondition;

        if ($whereClause) {
            $sql .= ' WHERE ' . $whereClause;
        }

        if (!empty($groupBy)) {
            $sql .= ' GROUP BY ' . implode(',', array_map(fn($field) => "s." . substr($field, strpos($field, '.') + 1), $groupBy));
        }

        $sql .= ' ORDER BY total_preparations DESC';

        $rsm = new ResultSetMapping();
        foreach ($groupBy as $field) {
            $fieldName = substr($field, strpos($field, '.') + 1);
            $rsm->addScalarResult($fieldName, $fieldName, 'string');
        }

        // Configuration du mapping des résultats
        $rsm->addScalarResult('total_preparations', 'total_preparations', 'integer');
        $rsm->addScalarResult('preparations_terminees', 'preparations_terminees', 'integer');
        $rsm->addScalarResult('preparations_en_cours', 'preparations_en_cours', 'integer');
        $rsm->addScalarResult('preparations_terminees_client', 'preparations_terminees_client', 'integer');
        $rsm->addScalarResult('preparations_en_cours_client', 'preparations_en_cours_client', 'integer');
        $rsm->addScalarResult('preparations_terminees_agence', 'preparations_terminees_agence', 'integer');
        $rsm->addScalarResult('preparations_en_cours_agence', 'preparations_en_cours_agence', 'integer');
        $rsm->addScalarResult('total_preparations_client', 'total_preparations_client', 'integer');
        $rsm->addScalarResult('preparations_client_en_cours', 'preparations_client_en_cours', 'integer');
        $rsm->addScalarResult('total_preparations_agence', 'total_preparations_agence', 'integer');
        $rsm->addScalarResult('preparations_agence_en_cours', 'preparations_agence_en_cours', 'integer');
        $rsm->addScalarResult('total_palettes', 'total_palettes', 'integer');
        $rsm->addScalarResult('total_colis', 'total_colis', 'integer');
        $rsm->addScalarResult('pourcentage_avancement', 'pourcentage_avancement', 'float');
        $rsm->addScalarResult('preparateurs', 'preparateurs', 'string');

        $query = $repository->getEntityManager()
            ->createNativeQuery($sql, $rsm)
            ->setParameter('startOfDay', $startOfDay ?? new \DateTime('today'))
            ->setParameter('endOfDay', $endOfDay ?? new \DateTime('today 23:59:59'))
            ->setParameter('status_ok', self::STATUS_OK)
            ->setParameter('client_prefix', self::CLIENT_PREFIX)
            ->setParameter('agency_prefix', self::AGENCY_PREFIX);

        if ($parameters) {
            if (is_array($parameters)) {
                foreach ($parameters as $key => $value) {
                    $query->setParameter('param_' . $key, $value);
                }
            } else {
                $query->setParameter('param_0', $parameters);
            }
        }

        $result = empty($groupBy) ? $query->getSingleResult() : $query->getResult();

        if (is_array($result) && !empty($groupBy)) {
            foreach ($result as &$row) {
                if (isset($row['preparateurs'])) {
                    $preparateurs = explode(', ', $row['preparateurs']);
                    $row['preparateurs'] = array_filter($preparateurs, fn($p) => !empty($p));
                }
            }
        }

        return $result;
    }

    // Les autres méthodes restent inchangées sauf pour les paramètres WHERE qui doivent utiliser des paramètres nommés
    private function getStatsParEtat(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery($repository, [], null, null, $startOfDay, $endOfDay);
    }

    private function getStatsParPreparateur(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Preparateur'],
            's.Preparateur IS NOT NULL AND s.Preparateur != \'\'',
            null,
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsGlobales(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery($repository, [], null, null, $startOfDay, $endOfDay);
    }

    private function getStatsParAgence(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client'],
            's.Code_Client LIKE :param_0',
            [self::AGENCY_PREFIX],
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsParClient(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client'],
            's.Code_Client LIKE :param_0',
            [self::CLIENT_PREFIX],
            $startOfDay,
            $endOfDay
        );
    }

    
    // Les méthodes prepareDataPreparateurs et prepareDataAgences restent inchangées
    private function prepareDataPreparateurs(array $statsPreparateur): array
    {
        $data = [
            'labels' => [],
            'termineesClient' => [],
            'enCoursClient' => [],
            'termineesAgence' => [],
            'enCoursAgence' => [],
            'pourcentages' => []
        ];

        foreach ($statsPreparateur as $stat) {
            $data['labels'][] = $stat['Preparateur'];
            $data['termineesClient'][] = $stat['preparations_terminees_client'];
            $data['enCoursClient'][] = $stat['preparations_en_cours_client'];
            $data['termineesAgence'][] = $stat['preparations_terminees_agence'];
            $data['enCoursAgence'][] = $stat['preparations_en_cours_agence'];
            $data['pourcentages'][] = $stat['pourcentage_avancement'];
        }

        return $data;
    }

    private function prepareDataAgences(array $statsAgence): array
    {
        $data = [
            'labels' => [],
            'terminees' => [],
            'enCours' => [],
            'pourcentages' => []
        ];

        foreach ($statsAgence as $stat) {
            $data['labels'][] = $stat['Client'];
            $data['terminees'][] = $stat['preparations_terminees'];
            $data['enCours'][] = $stat['preparations_en_cours'];
            $data['pourcentages'][] = $stat['pourcentage_avancement'];
        }

        return $data;
    }
}   