<?php

namespace App\Controller;

use App\Entity\SuividupreparationdujourRea;
use App\Repository\SuividupreparationdujourReaRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class SuividupreparationdujourReaController extends AbstractController
{
    private const STATUS_OK = 'Ok';
    private const AGENCY_PREFIX = 'CI999%';
    private const CLIENT_PREFIX = 'C0%';
    private const CLIENT_GSB_PREFIX = 'TH';
    private const CLIENT_GSB_LEROY_MERLIN = 'LEROY MERLIN';
    private const STATUS_KO = 'KO';
    private const STATUS_NON_FLASHE = 'NON FLASHE';

    /**
     * On garde exactement ta route :
     * /suividupreparationdujour/rea  name: app_suividupreparationdujour_rea
     */
    #[Route('/suividupreparationdujour/rea', name: 'app_suividupreparationdujour_rea')]
    public function index(SuividupreparationdujourReaRepository $repository): Response
    {
        $startOfDay = new \DateTimeImmutable('today 00:00:00');
        $endOfDay   = new \DateTimeImmutable('tomorrow 00:00:00');

        // lastUpdatedAt via DQL (simple et portable)
        $lastUpdatedAt = $repository->createQueryBuilder('s')
            ->select('MAX(s.updatedAt) AS lastUpdatedAt')
            ->andWhere('s.updatedAt >= :start')
            ->andWhere('s.updatedAt < :end')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        // Stats (requêtes SQL natives avec alias explicites côté SELECT)
        $statsGlobales         = $this->getStatsGlobales($repository, $startOfDay, $endOfDay);
        $statsAgence           = $this->getStatsParAgence($repository, $startOfDay, $endOfDay);
        $statsPreparateur      = $this->getStatsParPreparateur($repository, $startOfDay, $endOfDay);
        $statsClient           = $this->getStatsParClient($repository, $startOfDay, $endOfDay);
        $statsClientGSB        = $this->getStatsParClientGSB($repository, $startOfDay, $endOfDay);
        $statsClientGSBLM      = $this->getStatsParClientGSBLM($repository, $startOfDay, $endOfDay);
        $statsClientGSBAutres  = $this->getStatsParClientGSBAutres($repository, $startOfDay, $endOfDay);

        return $this->render('suividupreparationdujour_rea/stats.html.twig', [
            'statsStatut'                        => $this->getStatsParEtat($repository, $startOfDay, $endOfDay),
            'statsPreparateur'                   => $statsPreparateur,
            'statsGlobales'                      => $statsGlobales,
            'statsAgence'                        => $statsAgence,
            'statsClient'                        => $statsClient,
            'statsClientGSB'                     => $statsClientGSB,
            'statsClientGSBLM'                   => $statsClientGSBLM,
            'statsClientGSBAutres'               => $statsClientGSBAutres,
            'dataPreparateurs'                   => $this->prepareDataPreparateurs($statsPreparateur),
            'dataAgences'                        => $this->prepareDataAgences($statsAgence),
            'lastUpdatedAt'                      => $lastUpdatedAt,
            // exposés individuellement pour le header (comme dans ton ancien contrôleur)
            'totalPreparationsClientGSB'         => $statsGlobales['total_preparations_client_gsb'] ?? 0,
            'preparationsClientGSBEnCours'       => $statsGlobales['preparations_client_gsb_en_cours'] ?? 0,
            'preparationsTermineesClientGSB'     => $statsGlobales['preparations_terminees_client_gsb'] ?? 0,
            'totalPreparationsClientGSBLM'       => $statsGlobales['total_preparations_client_gsb_lm'] ?? 0,
            'preparationsClientGSBLMEnCours'     => $statsGlobales['preparations_client_gsb_lm_en_cours'] ?? 0,
            'preparationsTermineesClientGSBLM'   => $statsGlobales['preparations_terminees_client_gsb_lm'] ?? 0,
            'totalPreparationsClientGSBAutres'   => $statsGlobales['total_preparations_client_gsb_autres'] ?? 0,
            'preparationsClientGSBAutresEnCours' => $statsGlobales['preparations_client_gsb_autres_en_cours'] ?? 0,
            'preparationsTermineesClientGSBAutres' => $statsGlobales['preparations_terminees_client_gsb_autres'] ?? 0,
            'preparationsAgenceStatusKO'         => $statsGlobales['preparations_agence_status_ko'] ?? 0,
            'preparationsNonFlasheAvecDate'      => $statsGlobales['preparations_non_flashe_avec_date'] ?? 0,
        ]);
    }

    /** ========================== Helpers SQL natifs (avec alias explicites) ========================== */

    private function executeStatsQueryRea(
        SuividupreparationdujourReaRepository $repository,
        array $groupBy = [],
        ?string $whereClause = null,
        string|array|null $parameters = null,
        \DateTimeImmutable $startOfDay = null,
        \DateTimeImmutable $endOfDay = null
    ): array {
        $em    = $repository->getEntityManager();
        $conn  = $em->getConnection();
        $table = $em->getClassMetadata(SuividupreparationdujourRea::class)->getTableName();

        // Construction du SELECT
        $selectFields = [];
        foreach ($groupBy as $field) {
            $name = substr($field, strpos($field, '.') + 1);

            if ($name === 'Preparateur') {
                $selectFields[] = "CASE 
                    WHEN s.preparateur REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}'
                    THEN 'Ligne suspendus'
                    ELSE s.preparateur
                END AS Preparateur";
            } else {
                // map vers colonnes réelles (snake_case) puis alias EXACT attendu par Twig
                $column = match ($name) {
                    'Client'      => 's.client',
                    'Code_Client' => 's.code_client',
                    'No_Cmd'      => 's.no_cmd',
                    default       => 's.' . strtolower($name),
                };
                $selectFields[] = $column . ' AS ' . $name; // alias explicite !
            }
        }

        // Agrégats communs
        $selectFields = array_merge($selectFields, [
            'COUNT(s.id) AS total_preparations',
            "SUM(CASE WHEN s.flasher = :status_ok THEN 1 ELSE 0 END) AS preparations_terminees",
            "SUM(CASE WHEN s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko THEN 1 ELSE 0 END) AS preparations_en_cours",

            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.flasher = :status_ok THEN 1 ELSE 0 END) AS preparations_terminees_client",
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END) AS preparations_en_cours_client",

            "SUM(CASE WHEN s.code_client LIKE :agency_prefix AND s.flasher = :status_ok THEN 1 ELSE 0 END) AS preparations_terminees_agence",
            "SUM(CASE WHEN s.code_client LIKE :agency_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END) AS preparations_en_cours_agence",

            "SUM(CASE WHEN s.code_client LIKE :client_prefix THEN 1 ELSE 0 END) AS total_preparations_client",
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END) AS preparations_client_en_cours",

            "SUM(CASE WHEN s.code_client LIKE :agency_prefix THEN 1 ELSE 0 END) AS total_preparations_agence",
            "SUM(CASE WHEN s.code_client LIKE :agency_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END) AS preparations_agence_en_cours",

            // GSB / TH
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix THEN 1 ELSE 0 END) AS total_preparations_client_gsb",
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.flasher = :status_ok THEN 1 ELSE 0 END) AS preparations_terminees_client_gsb",
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END) AS preparations_client_gsb_en_cours",

            // GSB Leroy Merlin
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client LIKE :client_gsb_leroy_merlin THEN 1 ELSE 0 END) AS total_preparations_client_gsb_lm",
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client LIKE :client_gsb_leroy_merlin AND s.flasher = :status_ok THEN 1 ELSE 0 END) AS preparations_terminees_client_gsb_lm",
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client LIKE :client_gsb_leroy_merlin AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END) AS preparations_client_gsb_lm_en_cours",

            // GSB autres
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client NOT LIKE :client_gsb_leroy_merlin THEN 1 ELSE 0 END) AS total_preparations_client_gsb_autres",
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client NOT LIKE :client_gsb_leroy_merlin AND s.flasher = :status_ok THEN 1 ELSE 0 END) AS preparations_terminees_client_gsb_autres",
            "SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client NOT LIKE :client_gsb_leroy_merlin AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END) AS preparations_client_gsb_autres_en_cours",

            // KO & suspendues
            "SUM(CASE WHEN s.flasher = :status_ko THEN 1 ELSE 0 END) AS preparations_status_ko",
            "SUM(CASE WHEN s.code_client LIKE :agency_prefix AND s.flasher = :status_ko THEN 1 ELSE 0 END) AS preparations_agence_status_ko",
            "SUM(CASE WHEN s.flasher = :status_non_flashe AND s.preparateur REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' THEN 1 ELSE 0 END) AS preparations_non_flashe_avec_date",

            // Volumes + %
            "SUM(s.nb_pal) AS total_palettes",
            "SUM(s.nb_col) AS total_colis",
            "CAST(CASE WHEN COUNT(s.id) > 0 THEN (SUM(CASE WHEN s.flasher = :status_ok THEN 1 ELSE 0 END) * 100.0) / COUNT(s.id) ELSE 0 END AS DECIMAL(10,2)) AS pourcentage_avancement",

            // Liste préparateurs (hors dates) pour affichage
            "GROUP_CONCAT(DISTINCT CASE WHEN (s.flasher IS NULL OR s.flasher = '') AND s.preparateur NOT REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' THEN s.preparateur END SEPARATOR ', ') AS preparateurs",
        ]);

        $sql = 'SELECT ' . implode(', ', $selectFields) . " FROM {$table} s";

        $dateCondition = ' s.updated_at >= :startOfDay AND s.updated_at < :endOfDay';
        if (!empty($whereClause)) {
            $sql .= ' WHERE ' . $whereClause . ' AND ' . $dateCondition;
        } else {
            $sql .= ' WHERE ' . $dateCondition;
        }

        if (!empty($groupBy)) {
            $groupByFields = [];
            foreach ($groupBy as $field) {
                $name = substr($field, strpos($field, '.') + 1);
                if ($name === 'Preparateur') {
                    $groupByFields[] = "CASE 
                        WHEN s.preparateur REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}'
                        THEN 'Ligne suspendus'
                        ELSE s.preparateur
                    END";
                } else {
                    $groupByFields[] = match ($name) {
                        'Client'      => 's.client',
                        'Code_Client' => 's.code_client',
                        'No_Cmd'      => 's.no_cmd',
                        default       => 's.' . strtolower($name),
                    };
                }
            }
            $sql .= ' GROUP BY ' . implode(',', $groupByFields);
        }

        $sql .= ' ORDER BY total_preparations DESC';

        $rsm = new ResultSetMapping();
        foreach ($groupBy as $field) {
            $name = substr($field, strpos($field, '.') + 1);
            $rsm->addScalarResult($name, $name, 'string'); // doit matcher l'alias du SELECT
        }
        // mapping des agrégats
        foreach ([
            'total_preparations','preparations_terminees','preparations_en_cours',
            'preparations_terminees_client','preparations_en_cours_client',
            'preparations_terminees_agence','preparations_en_cours_agence',
            'total_preparations_client','preparations_client_en_cours',
            'total_preparations_agence','preparations_agence_en_cours',
            'total_preparations_client_gsb','preparations_terminees_client_gsb','preparations_client_gsb_en_cours',
            'total_preparations_client_gsb_lm','preparations_terminees_client_gsb_lm','preparations_client_gsb_lm_en_cours',
            'total_preparations_client_gsb_autres','preparations_terminees_client_gsb_autres','preparations_client_gsb_autres_en_cours',
            'total_palettes','total_colis','preparations_status_ko','preparations_agence_status_ko','preparations_non_flashe_avec_date'
        ] as $intCol) {
            $rsm->addScalarResult($intCol, $intCol, 'integer');
        }
        $rsm->addScalarResult('pourcentage_avancement', 'pourcentage_avancement', 'float');
        $rsm->addScalarResult('preparateurs', 'preparateurs', 'string');

        $query = $em->createNativeQuery($sql, $rsm)
            ->setParameter('startOfDay', $startOfDay ?? new \DateTimeImmutable('today 00:00:00'))
            ->setParameter('endOfDay', $endOfDay ?? new \DateTimeImmutable('tomorrow 00:00:00'))
            ->setParameter('status_ok', self::STATUS_OK)
            ->setParameter('status_ko', self::STATUS_KO)
            ->setParameter('status_non_flashe', self::STATUS_NON_FLASHE)
            ->setParameter('client_prefix', self::CLIENT_PREFIX)
            ->setParameter('agency_prefix', self::AGENCY_PREFIX)
            ->setParameter('client_gsb_prefix', '%' . self::CLIENT_GSB_PREFIX . '%')
            ->setParameter('client_gsb_leroy_merlin', self::CLIENT_GSB_LEROY_MERLIN . '%');

        if ($parameters) {
            if (is_array($parameters)) {
                foreach ($parameters as $i => $value) {
                    $query->setParameter('param_' . $i, $value);
                }
            } else {
                $query->setParameter('param_0', $parameters);
            }
        }

        $result = empty($groupBy) ? $query->getSingleResult() : $query->getResult();

        // transformer la liste de préparateurs string -> array (lisible en Twig)
        if (is_array($result) && !empty($groupBy)) {
            foreach ($result as &$row) {
                if (isset($row['preparateurs'])) {
                    $preps = explode(', ', (string)$row['preparateurs']);
                    $row['preparateurs'] = array_values(array_filter($preps, fn($p) => !empty($p)));
                }
            }
        }

        return $result;
    }

    private function getStatsParEtat(SuividupreparationdujourReaRepository $repository, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->executeStatsQueryRea($repository, [], null, null, $start, $end);
    }

    private function getStatsParPreparateur(SuividupreparationdujourReaRepository $repository, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->executeStatsQueryRea(
            $repository,
            ['s.Preparateur'],
            "s.preparateur IS NOT NULL AND s.preparateur != ''",
            null,
            $start,
            $end
        );
    }

    private function getStatsGlobales(SuividupreparationdujourReaRepository $repository, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->executeStatsQueryRea($repository, [], null, null, $start, $end);
    }

    private function getStatsParAgence(SuividupreparationdujourReaRepository $repository, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->executeStatsQueryRea(
            $repository,
            ['s.Client', 's.Code_Client'],
            's.code_client LIKE :param_0',
            [self::AGENCY_PREFIX],
            $start,
            $end
        );
    }

    private function getStatsParClient(SuividupreparationdujourReaRepository $repository, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->executeStatsQueryRea(
            $repository,
            ['s.Client', 's.Code_Client'],
            's.code_client LIKE :param_0',
            [self::CLIENT_PREFIX],
            $start,
            $end
        );
    }

    private function getStatsParClientGSB(SuividupreparationdujourReaRepository $repository, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->executeStatsQueryRea(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.code_client LIKE :param_0 AND s.no_cmd LIKE :param_1',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%'],
            $start,
            $end
        );
    }

    private function getStatsParClientGSBLM(SuividupreparationdujourReaRepository $repository, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->executeStatsQueryRea(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.code_client LIKE :param_0 AND s.no_cmd LIKE :param_1 AND s.client LIKE :param_2',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%', self::CLIENT_GSB_LEROY_MERLIN . '%'],
            $start,
            $end
        );
    }

    private function getStatsParClientGSBAutres(SuividupreparationdujourReaRepository $repository, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->executeStatsQueryRea(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.code_client LIKE :param_0 AND s.no_cmd LIKE :param_1 AND s.client NOT LIKE :param_2',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%', self::CLIENT_GSB_LEROY_MERLIN . '%'],
            $start,
            $end
        );
    }

    /** ============================== Préparation des datasets pour Chart.js ============================== */

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

            $data['termineesClient'][]         = ($stat['preparations_terminees_client'] ?? 0) - $clientGSBTerminees;
            $data['enCoursClient'][]           = ($stat['preparations_en_cours_client'] ?? 0) - $clientGSBEnCours;

            $data['termineesClientGSBLM'][]    = $stat['preparations_terminees_client_gsb_lm'] ?? 0;
            $data['enCoursClientGSBLM'][]      = $stat['preparations_client_gsb_lm_en_cours'] ?? 0;

            $data['termineesClientGSBAutres'][] = $stat['preparations_terminees_client_gsb_autres'] ?? 0;
            $data['enCoursClientGSBAutres'][]   = $stat['preparations_client_gsb_autres_en_cours'] ?? 0;

            $data['termineesAgence'][]         = $stat['preparations_terminees_agence'] ?? 0;
            $data['enCoursAgence'][]           = $stat['preparations_en_cours_agence'] ?? 0;
            $data['pourcentages'][]            = (float)($stat['pourcentage_avancement'] ?? 0.0);
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
            $data['labels'][]      = $stat['Client']; // grâce aux alias SQL
            $data['terminees'][]   = (int)($stat['preparations_terminees'] ?? 0);
            $data['enCours'][]     = (int)($stat['preparations_en_cours'] ?? 0);
            $data['pourcentages'][] = (float)($stat['pourcentage_avancement'] ?? 0.0);
        }

        return $data;
    }
}
