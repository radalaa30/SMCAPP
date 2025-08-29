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
    private const CLIENT_GSB_PREFIX = 'TH';
    private const CLIENT_GSB_LEROY_MERLIN = 'LEROY MERLIN';
    private const STATUS_KO = 'KO';
    private const STATUS_NON_FLASHE = 'NON FLASHE';

    #[Route('/suivi/stats', name: 'app_suivi_stats')]
    public function stats(SuividupreparationdujourRepository $repository): Response
    {
        $startOfDay = new \DateTime('today');
        $endOfDay   = new \DateTime('today 23:59:59');

        $lastUpdatedAt = $repository->createQueryBuilder('s')
            ->select('MAX(s.updatedAt) AS lastUpdatedAt')
            ->where('s.updatedAt BETWEEN :startOfDay AND :endOfDay')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        $statsGlobales         = $this->getStatsGlobales($repository, $startOfDay, $endOfDay);
        $statsAgence           = $this->getStatsParAgence($repository, $startOfDay, $endOfDay);
        $statsPreparateur      = $this->getStatsParPreparateur($repository, $startOfDay, $endOfDay);
        $statsClient           = $this->getStatsParClient($repository, $startOfDay, $endOfDay);
        $statsClientGSB        = $this->getStatsParClientGSB($repository, $startOfDay, $endOfDay);
        $statsClientGSBLM      = $this->getStatsParClientGSBLM($repository, $startOfDay, $endOfDay);
        $statsClientGSBAutres  = $this->getStatsParClientGSBAutres($repository, $startOfDay, $endOfDay);
        $statsTransporteur     = $this->getStatsParTransporteur($repository, $startOfDay, $endOfDay);

        return $this->render('suividupreparationdujour/stats.html.twig', [
            'statsStatut'                     => $this->getStatsParEtat($repository, $startOfDay, $endOfDay),
            'statsPreparateur'                => $statsPreparateur,
            'statsGlobales'                   => $statsGlobales,
            'statsAgence'                     => $statsAgence,
            'statsClient'                     => $statsClient,
            'statsClientGSB'                  => $statsClientGSB,
            'statsClientGSBLM'                => $statsClientGSBLM,
            'statsClientGSBAutres'            => $statsClientGSBAutres,
            'statsTransporteur'               => $statsTransporteur,
            'dataPreparateurs'                => $this->prepareDataPreparateurs($statsPreparateur),
            'dataAgences'                     => $this->prepareDataAgences($statsAgence),
            'dataTransporteurs'               => $this->prepareDataTransporteurs($statsTransporteur),
            'lastUpdatedAt'                   => $lastUpdatedAt,
            'totalPreparationsClient'         => $statsGlobales['total_preparations_client'] ?? 0,
            'preparationsClientEnCours'       => $statsGlobales['preparations_client_en_cours'] ?? 0,
            'totalPreparationsAgence'         => $statsGlobales['total_preparations_agence'] ?? 0,
            'preparationsAgenceEnCours'       => $statsGlobales['preparations_agence_en_cours'] ?? 0,
            'totalPreparationsClientGSB'      => $statsGlobales['total_preparations_client_gsb'] ?? 0,
            'preparationsClientGSBEnCours'    => $statsGlobales['preparations_client_gsb_en_cours'] ?? 0,
            'preparationsTermineesClientGSB'  => $statsGlobales['preparations_terminees_client_gsb'] ?? 0,
            'totalPreparationsClientGSBLM'    => $statsGlobales['total_preparations_client_gsb_lm'] ?? 0,
            'preparationsClientGSBLMEnCours'  => $statsGlobales['preparations_client_gsb_lm_en_cours'] ?? 0,
            'preparationsTermineesClientGSBLM'=> $statsGlobales['preparations_terminees_client_gsb_lm'] ?? 0,
            'totalPreparationsClientGSBAutres'=> $statsGlobales['total_preparations_client_gsb_autres'] ?? 0,
            'preparationsClientGSBAutresEnCours' => $statsGlobales['preparations_client_gsb_autres_en_cours'] ?? 0,
            'preparationsTermineesClientGSBAutres'=> $statsGlobales['preparations_terminees_client_gsb_autres'] ?? 0,
            'preparationsAgenceStatusKO'      => $statsGlobales['preparations_agence_status_ko'] ?? 0,
            'preparationsNonFlasheAvecDate'   => $statsGlobales['preparations_non_flashe_avec_date'] ?? 0,
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
            if ($fieldName === 'Preparateur') {
                $selectFields[] = "CASE 
                    WHEN s.Preparateur REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' 
                    THEN 'Ligne suspendus' 
                    ELSE s.Preparateur 
                END as Preparateur";
            } else {
                $selectFields[] = "s.$fieldName";
            }
        }

        $selectFields = array_merge($selectFields, [
            'COUNT(s.id) as total_preparations',
            'SUM(CASE WHEN s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees',
            'SUM(CASE WHEN s.Flasher IS NULL OR s.Flasher = \'\' OR s.Flasher = :status_ko THEN 1 ELSE 0 END) as preparations_en_cours',

            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees_client',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\' OR s.Flasher = :status_ko) THEN 1 ELSE 0 END) as preparations_en_cours_client',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix AND s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees_agence',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\' OR s.Flasher = :status_ko) THEN 1 ELSE 0 END) as preparations_en_cours_agence',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix THEN 1 ELSE 0 END) as total_preparations_client',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\' OR s.Flasher = :status_ko) THEN 1 ELSE 0 END) as preparations_client_en_cours',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix THEN 1 ELSE 0 END) as total_preparations_agence',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\' OR s.Flasher = :status_ko) THEN 1 ELSE 0 END) as preparations_agence_en_cours',

            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix THEN 1 ELSE 0 END) as total_preparations_client_gsb',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix AND s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees_client_gsb',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix AND (s.Flasher IS NULL OR s.Flasher = \'\' OR s.Flasher = :status_ko) THEN 1 ELSE 0 END) as preparations_client_gsb_en_cours',

            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix AND s.Client LIKE :client_gsb_leroy_merlin THEN 1 ELSE 0 END) as total_preparations_client_gsb_lm',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix AND s.Client LIKE :client_gsb_leroy_merlin AND s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees_client_gsb_lm',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix AND s.Client LIKE :client_gsb_leroy_merlin AND (s.Flasher IS NULL OR s.Flasher = \'\' OR s.Flasher = :status_ko) THEN 1 ELSE 0 END) as preparations_client_gsb_lm_en_cours',

            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix AND s.Client NOT LIKE :client_gsb_leroy_merlin THEN 1 ELSE 0 END) as total_preparations_client_gsb_autres',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix AND s.Client NOT LIKE :client_gsb_leroy_merlin AND s.Flasher = :status_ok THEN 1 ELSE 0 END) as preparations_terminees_client_gsb_autres',
            'SUM(CASE WHEN s.Code_Client LIKE :client_prefix AND s.No_Cmd LIKE :client_gsb_prefix AND s.Client NOT LIKE :client_gsb_leroy_merlin AND (s.Flasher IS NULL OR s.Flasher = \'\' OR s.Flasher = :status_ko) THEN 1 ELSE 0 END) as preparations_client_gsb_autres_en_cours',

            'SUM(CASE WHEN s.Flasher = :status_ko THEN 1 ELSE 0 END) as preparations_status_ko',
            'SUM(CASE WHEN s.Code_Client LIKE :agency_prefix AND s.Flasher = :status_ko THEN 1 ELSE 0 END) as preparations_agence_status_ko',

            'SUM(CASE 
                WHEN s.Flasher = :status_non_flashe 
                 AND s.Preparateur REGEXP \'^[0-9]{2}/[0-9]{2}/[0-9]{4}\' 
                THEN 1 ELSE 0 END) as preparations_non_flashe_avec_date',

            'SUM(s.Nb_Pal) as total_palettes',
            'SUM(s.Nb_col) as total_colis',

            'CAST(
                CASE 
                    WHEN COUNT(s.id) > 0 THEN 
                        (SUM(CASE WHEN s.Flasher = :status_ok THEN 1 ELSE 0 END) * 100.0) / COUNT(s.id)
                    ELSE 0 
                END 
            AS DECIMAL(10,2)) as pourcentage_avancement',

            "GROUP_CONCAT(DISTINCT CASE 
                WHEN (s.Flasher IS NULL OR s.Flasher = '') 
                 AND s.Preparateur NOT REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' 
                THEN s.Preparateur 
            END SEPARATOR ', ') as preparateurs"
        ]);

        $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM suividupreparationdujour s';

        $dateCondition = ' s.updatedAt BETWEEN :startOfDay AND :endOfDay';
        $whereClause = $whereClause ? $whereClause . ' AND ' . $dateCondition : $dateCondition;

        if ($whereClause) {
            $sql .= ' WHERE ' . $whereClause;
        }

        if (!empty($groupBy)) {
            $groupByFields = [];
            foreach ($groupBy as $field) {
                $fieldName = substr($field, strpos($field, '.') + 1);
                if ($fieldName === 'Preparateur') {
                    $groupByFields[] = "CASE 
                        WHEN s.Preparateur REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' 
                        THEN 'Ligne suspendus' 
                        ELSE s.Preparateur 
                    END";
                } else {
                    $groupByFields[] = "s.$fieldName";
                }
            }
            $sql .= ' GROUP BY ' . implode(',', $groupByFields);
        }

        $sql .= ' ORDER BY total_preparations DESC';

        $rsm = new ResultSetMapping();
        foreach ($groupBy as $field) {
            $fieldName = substr($field, strpos($field, '.') + 1);
            $rsm->addScalarResult($fieldName, $fieldName, 'string');
        }

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

        $rsm->addScalarResult('total_preparations_client_gsb', 'total_preparations_client_gsb', 'integer');
        $rsm->addScalarResult('preparations_terminees_client_gsb', 'preparations_terminees_client_gsb', 'integer');
        $rsm->addScalarResult('preparations_client_gsb_en_cours', 'preparations_client_gsb_en_cours', 'integer');

        $rsm->addScalarResult('total_preparations_client_gsb_lm', 'total_preparations_client_gsb_lm', 'integer');
        $rsm->addScalarResult('preparations_terminees_client_gsb_lm', 'preparations_terminees_client_gsb_lm', 'integer');
        $rsm->addScalarResult('preparations_client_gsb_lm_en_cours', 'preparations_client_gsb_lm_en_cours', 'integer');

        $rsm->addScalarResult('total_preparations_client_gsb_autres', 'total_preparations_client_gsb_autres', 'integer');
        $rsm->addScalarResult('preparations_terminees_client_gsb_autres', 'preparations_terminees_client_gsb_autres', 'integer');
        $rsm->addScalarResult('preparations_client_gsb_autres_en_cours', 'preparations_client_gsb_autres_en_cours', 'integer');

        $rsm->addScalarResult('total_palettes', 'total_palettes', 'integer');
        $rsm->addScalarResult('total_colis', 'total_colis', 'integer');
        $rsm->addScalarResult('pourcentage_avancement', 'pourcentage_avancement', 'float');
        $rsm->addScalarResult('preparateurs', 'preparateurs', 'string');
        $rsm->addScalarResult('preparations_status_ko', 'preparations_status_ko', 'integer');
        $rsm->addScalarResult('preparations_agence_status_ko', 'preparations_agence_status_ko', 'integer');
        $rsm->addScalarResult('preparations_non_flashe_avec_date', 'preparations_non_flashe_avec_date', 'integer');

        $query = $repository->getEntityManager()
            ->createNativeQuery($sql, $rsm)
            ->setParameter('startOfDay', $startOfDay ?? new \DateTime('today'))
            ->setParameter('endOfDay', $endOfDay ?? new \DateTime('today 23:59:59'))
            ->setParameter('status_ok', self::STATUS_OK)
            ->setParameter('status_ko', self::STATUS_KO)
            ->setParameter('status_non_flashe', self::STATUS_NON_FLASHE)
            ->setParameter('client_prefix', self::CLIENT_PREFIX)
            ->setParameter('agency_prefix', self::AGENCY_PREFIX)
            ->setParameter('client_gsb_prefix', '%' . self::CLIENT_GSB_PREFIX . '%')
            ->setParameter('client_gsb_leroy_merlin', self::CLIENT_GSB_LEROY_MERLIN . '%');

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

    private function getStatsParClientGSB(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.Code_Client LIKE :param_0 AND s.No_Cmd LIKE :param_1',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%'],
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsParClientGSBLM(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.Code_Client LIKE :param_0 AND s.No_Cmd LIKE :param_1 AND s.Client LIKE :param_2',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%', self::CLIENT_GSB_LEROY_MERLIN . '%'],
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsParClientGSBAutres(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.Code_Client LIKE :param_0 AND s.No_Cmd LIKE :param_1 AND s.Client NOT LIKE :param_2',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%', self::CLIENT_GSB_LEROY_MERLIN . '%'],
            $startOfDay,
            $endOfDay
        );
    }

    /** NOUVEAU : par transporteur */
    private function getStatsParTransporteur(SuividupreparationdujourRepository $repository, \DateTime $startOfDay, \DateTime $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Transporteur'],
            's.Transporteur IS NOT NULL AND s.Transporteur != \'\'',
            null,
            $startOfDay,
            $endOfDay
        );
    }

    private function prepareDataPreparateurs(array $statsPreparateur): array
    {
        $data = [
            'labels' => [],
            'termineesClient' => [],
            'enCoursClient' => [],
            'termineesClientGSBLM' => [],
            'enCoursClientGSBLM' => [],
            'termineesClientGSBAutres' => [],
            'enCoursClientGSBAutres' => [],
            'termineesAgence' => [],
            'enCoursAgence' => [],
            'pourcentages' => []
        ];

        foreach ($statsPreparateur as $stat) {
            $data['labels'][] = $stat['Preparateur'];
            $clientGSBTerminees = $stat['preparations_terminees_client_gsb'] ?? 0;
            $clientGSBEnCours   = $stat['preparations_client_gsb_en_cours'] ?? 0;

            $data['termineesClient'][]          = ($stat['preparations_terminees_client'] ?? 0) - $clientGSBTerminees;
            $data['enCoursClient'][]            = ($stat['preparations_en_cours_client'] ?? 0) - $clientGSBEnCours;

            $data['termineesClientGSBLM'][]     = $stat['preparations_terminees_client_gsb_lm'] ?? 0;
            $data['enCoursClientGSBLM'][]       = $stat['preparations_client_gsb_lm_en_cours'] ?? 0;

            $data['termineesClientGSBAutres'][] = $stat['preparations_terminees_client_gsb_autres'] ?? 0;
            $data['enCoursClientGSBAutres'][]   = $stat['preparations_client_gsb_autres_en_cours'] ?? 0;

            $data['termineesAgence'][]          = $stat['preparations_terminees_agence'] ?? 0;
            $data['enCoursAgence'][]            = $stat['preparations_en_cours_agence'] ?? 0;
            $data['pourcentages'][]             = (float)($stat['pourcentage_avancement'] ?? 0);
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
            $data['labels'][]       = $stat['Client'];
            $data['terminees'][]    = (int)($stat['preparations_terminees'] ?? 0);
            $data['enCours'][]      = (int)($stat['preparations_en_cours'] ?? 0);
            $data['pourcentages'][] = (float)($stat['pourcentage_avancement'] ?? 0);
        }

        return $data;
    }

    /** NOUVEAU : jeu de données pour le graphe Transport */
    private function prepareDataTransporteurs(array $stats): array
    {
        $data = [
            'labels' => [],
            'terminees' => [],
            'enCours' => [],
            'pourcentages' => [],
        ];

        foreach ($stats as $stat) {
            $data['labels'][]       = $stat['Transporteur'] ?? 'Inconnu';
            $data['terminees'][]    = (int)($stat['preparations_terminees'] ?? 0);
            $data['enCours'][]      = (int)($stat['preparations_en_cours'] ?? 0);
            $data['pourcentages'][] = (float)($stat['pourcentage_avancement'] ?? 0);
        }

        return $data;
    }
}
