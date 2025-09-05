<?php

namespace App\Controller;

use App\Repository\SuividupreparationdujourReaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\Query\ResultSetMapping;

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

    #[Route('/suividupreparationdujour/rea', name: 'app_suividupreparationdujour_rea')]
    public function stats(SuividupreparationdujourReaRepository $repository): Response
    {
        $tz = new \DateTimeZone('Europe/Paris');
        $startOfDay = (new \DateTimeImmutable('today', $tz))->setTime(0, 0, 0);
        $endOfDay   = $startOfDay->modify('+1 day'); // fenêtre demi-ouverte [start, end)

        $lastUpdatedAt = $repository->createQueryBuilder('s')
            ->select('MAX(s.updatedAt) AS lastUpdatedAt')
            ->where('s.updatedAt >= :startOfDay AND s.updatedAt < :endOfDay')
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

        // Prépare datasets pour les charts
        $dataPreparateurs  = $this->prepareDataPreparateurs($statsPreparateur);
        $dataAgences       = $this->prepareDataAgences($statsAgence);
        $dataTransporteurs = $this->prepareDataTransporteurs($statsTransporteur);

        // >>> Totaux transport pour le donut
        $totalTermineesTransport = array_sum($dataTransporteurs['terminees'] ?? []);
        $totalEnCoursTransport   = array_sum($dataTransporteurs['enCours'] ?? []);
        // <<<

        return $this->render('suividupreparationdujour_rea/stats.html.twig', [
            'statsStatut'                        => $this->getStatsParEtat($repository, $startOfDay, $endOfDay),
            'statsPreparateur'                   => $statsPreparateur,
            'statsGlobales'                      => $statsGlobales,
            'statsAgence'                        => $statsAgence,
            'statsClient'                        => $statsClient,
            'statsClientGSB'                     => $statsClientGSB,
            'statsClientGSBLM'                   => $statsClientGSBLM,
            'statsClientGSBAutres'               => $statsClientGSBAutres,
            'statsTransporteur'                  => $statsTransporteur,
            'dataPreparateurs'                   => $dataPreparateurs,
            'dataAgences'                        => $dataAgences,
            'dataTransporteurs'                  => $dataTransporteurs,
            'lastUpdatedAt'                      => $lastUpdatedAt,

            // Globaux déjà utilisés dans ton Twig
            'totalPreparationsClient'            => $statsGlobales['total_preparations_client'] ?? 0,
            'preparationsClientEnCours'          => $statsGlobales['preparations_client_en_cours'] ?? 0,
            'totalPreparationsAgence'            => $statsGlobales['total_preparations_agence'] ?? 0,
            'preparationsAgenceEnCours'          => $statsGlobales['preparations_agence_en_cours'] ?? 0,
            'totalPreparationsClientGSB'         => $statsGlobales['total_preparations_client_gsb'] ?? 0,
            'preparationsClientGSBEnCours'       => $statsGlobales['preparations_client_gsb_en_cours'] ?? 0,
            'preparationsTermineesClientGSB'     => $statsGlobales['preparations_terminees_client_gsb'] ?? 0,
            'totalPreparationsClientGSBLM'       => $statsGlobales['total_preparations_client_gsb_lm'] ?? 0,
            'preparationsClientGSBLMEnCours'     => $statsGlobales['preparations_client_gsb_lm_en_cours'] ?? 0,
            'preparationsTermineesClientGSBLM'   => $statsGlobales['preparations_terminees_client_gsb_lm'] ?? 0,
            'totalPreparationsClientGSBAutres'   => $statsGlobales['total_preparations_client_gsb_autres'] ?? 0,
            'preparationsClientGSBAutresEnCours' => $statsGlobales['preparations_client_gsb_autres_en_cours'] ?? 0,
            'preparationsTermineesClientGSBAutres'=> $statsGlobales['preparations_terminees_client_gsb_autres'] ?? 0,
            'preparationsAgenceStatusKO'         => $statsGlobales['preparations_agence_status_ko'] ?? 0,
            'preparationsNonFlasheAvecDate'      => $statsGlobales['preparations_non_flashe_avec_date'] ?? 0,

            // >>> Ces deux-là corrigent l’erreur Twig
            'totalTermineesTransport'            => $totalTermineesTransport,
            'totalEnCoursTransport'              => $totalEnCoursTransport,
            // <<<
        ]);
    }

    private function executeStatsQuery(
        SuividupreparationdujourReaRepository $repository,
        array $groupBy = [],
        ?string $whereClause = null,
        string|array|null $parameters = null,
        ?\DateTimeInterface $startOfDay = null,
        ?\DateTimeInterface $endOfDay = null
    ): array {
        $tz = new \DateTimeZone('Europe/Paris');
        $start = ($startOfDay ?? new \DateTimeImmutable('today', $tz))->setTime(0, 0, 0);
        $end   = ($endOfDay   ?? $start)->setTime(0, 0, 0)->modify('+1 day'); // [start, end)

        // Nom de table depuis la métadata Doctrine (sécurisé)
        $meta  = $repository->getEntityManager()->getClassMetadata($repository->getClassName());
        $table = $meta->getTableName();

        // Map "alias lisible" -> colonne DB
        $toDb = static function (string $fieldName): string {
            return match ($fieldName) {
                'Client'        => 'client',
                'Code_Client'   => 'code_client',
                'No_Cmd'        => 'no_cmd',
                'Preparateur'   => 'preparateur',
                'Transporteur'  => 'transporteur',
                default         => strtolower($fieldName),
            };
        };

        $selectFields = [];

        // Champs groupés avec alias stables
        foreach ($groupBy as $field) {
            $fieldName = substr($field, (int)strpos($field, '.') + 1);
            if ($fieldName === 'Preparateur') {
                // NOTE : on garde "Ligne suspendus" pour matcher le Twig existant
                $selectFields[] = "CASE 
                    WHEN s.preparateur REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' 
                    THEN 'Ligne suspendus' 
                    ELSE s.preparateur 
                END AS Preparateur";
            } else {
                $dbCol = $toDb($fieldName);
                $selectFields[] = "s.$dbCol AS $fieldName";
            }
        }

        // Agrégats
        $selectFields = array_merge($selectFields, [
            'COUNT(s.id) AS total_preparations',
            "COALESCE(SUM(CASE WHEN s.flasher = :status_ok THEN 1 ELSE 0 END),0) AS preparations_terminees",
            "COALESCE(SUM(CASE WHEN (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END),0) AS preparations_en_cours",

            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.flasher = :status_ok THEN 1 ELSE 0 END),0) AS preparations_terminees_client",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END),0) AS preparations_en_cours_client",

            "COALESCE(SUM(CASE WHEN s.code_client LIKE :agency_prefix AND s.flasher = :status_ok THEN 1 ELSE 0 END),0) AS preparations_terminees_agence",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :agency_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END),0) AS preparations_en_cours_agence",

            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix THEN 1 ELSE 0 END),0) AS total_preparations_client",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END),0) AS preparations_client_en_cours",

            "COALESCE(SUM(CASE WHEN s.code_client LIKE :agency_prefix THEN 1 ELSE 0 END),0) AS total_preparations_agence",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :agency_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END),0) AS preparations_agence_en_cours",

            // GSB global
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix THEN 1 ELSE 0 END),0) AS total_preparations_client_gsb",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.flasher = :status_ok THEN 1 ELSE 0 END),0) AS preparations_terminees_client_gsb",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END),0) AS preparations_client_gsb_en_cours",

            // GSB LM
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client LIKE :client_gsb_leroy_merlin THEN 1 ELSE 0 END),0) AS total_preparations_client_gsb_lm",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client LIKE :client_gsb_leroy_merlin AND s.flasher = :status_ok THEN 1 ELSE 0 END),0) AS preparations_terminees_client_gsb_lm",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client LIKE :client_gsb_leroy_merlin AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END),0) AS preparations_client_gsb_lm_en_cours",

            // GSB autres
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client NOT LIKE :client_gsb_leroy_merlin THEN 1 ELSE 0 END),0) AS total_preparations_client_gsb_autres",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client NOT LIKE :client_gsb_leroy_merlin AND s.flasher = :status_ok THEN 1 ELSE 0 END),0) AS preparations_terminees_client_gsb_autres",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :client_prefix AND s.no_cmd LIKE :client_gsb_prefix AND s.client NOT LIKE :client_gsb_leroy_merlin AND (s.flasher IS NULL OR s.flasher = '' OR s.flasher = :status_ko) THEN 1 ELSE 0 END),0) AS preparations_client_gsb_autres_en_cours",

            // KO
            "COALESCE(SUM(CASE WHEN s.flasher = :status_ko THEN 1 ELSE 0 END),0) AS preparations_status_ko",
            "COALESCE(SUM(CASE WHEN s.code_client LIKE :agency_prefix AND s.flasher = :status_ko THEN 1 ELSE 0 END),0) AS preparations_agence_status_ko",

            // NON FLASHE + date sur libellé préparateur formaté date
            "COALESCE(SUM(CASE 
                WHEN s.flasher = :status_non_flashe 
                 AND s.preparateur REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' 
                THEN 1 ELSE 0 END),0) AS preparations_non_flashe_avec_date",

            // totaux logistiques
            "COALESCE(SUM(s.nb_pal),0) AS total_palettes",
            "COALESCE(SUM(s.nb_col),0) AS total_colis",

            // % d'avancement
            "CAST(
                CASE 
                    WHEN COUNT(s.id) > 0 THEN 
                        (SUM(CASE WHEN s.flasher = :status_ok THEN 1 ELSE 0 END) * 100.0) / COUNT(s.id)
                    ELSE 0 
                END 
            AS DECIMAL(5,2)) AS pourcentage_avancement",

            // liste préparateurs non flashés
            "GROUP_CONCAT(DISTINCT CASE 
                WHEN (s.flasher IS NULL OR s.flasher = '') 
                 AND s.preparateur NOT REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' 
                THEN s.preparateur 
            END SEPARATOR ', ') AS preparateurs",
        ]);

        $sql = 'SELECT ' . implode(', ', $selectFields) . " FROM $table s";

        // filtre date (colonne DB = updated_at)
        $dateCondition = ' s.updated_at >= :startOfDay AND s.updated_at < :endOfDay';
        $whereClause = $whereClause ? $whereClause . ' AND ' . $dateCondition : $dateCondition;
        if ($whereClause) { $sql .= ' WHERE ' . $whereClause; }

        if (!empty($groupBy)) {
            $groupByFields = [];
            foreach ($groupBy as $field) {
                $fieldName = substr($field, (int)strpos($field, '.') + 1);
                if ($fieldName === 'Preparateur') {
                    $groupByFields[] = "CASE 
                        WHEN s.preparateur REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}' 
                        THEN 'Ligne suspendus' 
                        ELSE s.preparateur 
                    END";
                } else {
                    $groupByFields[] = 's.' . $toDb($fieldName);
                }
            }
            $sql .= ' GROUP BY ' . implode(', ', $groupByFields);
        }

        $sql .= ' ORDER BY total_preparations DESC';

        $rsm = new ResultSetMapping();
        foreach ($groupBy as $field) {
            $fieldName = substr($field, (int)strpos($field, '.') + 1);
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
            ->setParameter('startOfDay', $start)
            ->setParameter('endOfDay', $end)
            ->setParameter('status_ok', self::STATUS_OK)
            ->setParameter('status_ko', self::STATUS_KO)
            ->setParameter('status_non_flashe', self::STATUS_NON_FLASHE)
            ->setParameter('client_prefix', self::CLIENT_PREFIX)
            ->setParameter('agency_prefix', self::AGENCY_PREFIX)
            ->setParameter('client_gsb_prefix', '%' . self::CLIENT_GSB_PREFIX . '%')
            ->setParameter('client_gsb_leroy_merlin', self::CLIENT_GSB_LEROY_MERLIN . '%');

        if ($parameters) {
            foreach ((array)$parameters as $i => $value) {
                $query->setParameter('param_' . $i, $value);
            }
        }

        $result = empty($groupBy) ? $query->getSingleResult() : $query->getResult();

        if (is_array($result) && !empty($groupBy)) {
            foreach ($result as &$row) {
                if (isset($row['preparateurs'])) {
                    $row['preparateurs'] = array_values(array_filter(explode(', ', $row['preparateurs'])));
                }
            }
        }

        return $result;
    }

    private function getStatsParEtat(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery($repository, [], null, null, $startOfDay, $endOfDay);
    }

    private function getStatsParPreparateur(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Preparateur'],
            "s.preparateur IS NOT NULL AND s.preparateur != ''",
            null,
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsGlobales(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery($repository, [], null, null, $startOfDay, $endOfDay);
    }

    private function getStatsParAgence(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client'],
            's.code_client LIKE :param_0',
            [self::AGENCY_PREFIX],
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsParClient(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client'],
            's.code_client LIKE :param_0',
            [self::CLIENT_PREFIX],
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsParClientGSB(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.code_client LIKE :param_0 AND s.no_cmd LIKE :param_1',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%'],
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsParClientGSBLM(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.code_client LIKE :param_0 AND s.no_cmd LIKE :param_1 AND s.client LIKE :param_2',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%', self::CLIENT_GSB_LEROY_MERLIN . '%'],
            $startOfDay,
            $endOfDay
        );
    }

    private function getStatsParClientGSBAutres(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Client', 's.Code_Client', 's.No_Cmd'],
            's.code_client LIKE :param_0 AND s.no_cmd LIKE :param_1 AND s.client NOT LIKE :param_2',
            [self::CLIENT_PREFIX, '%' . self::CLIENT_GSB_PREFIX . '%', self::CLIENT_GSB_LEROY_MERLIN . '%'],
            $startOfDay,
            $endOfDay
        );
    }

    /** Transporteurs SANS LIV */
    private function getStatsParTransporteur(SuividupreparationdujourReaRepository $repository, \DateTimeInterface $startOfDay, \DateTimeInterface $endOfDay): array
    {
        return $this->executeStatsQuery(
            $repository,
            ['s.Transporteur'],
            "s.transporteur IS NOT NULL 
             AND s.transporteur != '' 
             AND s.transporteur != 'LIV'",
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

    /** Jeu de données pour le graphe Transport */
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
