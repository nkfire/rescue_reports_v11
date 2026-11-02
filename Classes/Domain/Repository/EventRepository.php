<?php
namespace Nkfire\RescueReports\Domain\Repository;

use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class EventRepository extends Repository
{
    protected $objectManager;

    public function injectObjectManager(ObjectManager $objectManager): void
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Holt ein einzelnes Event inkl. Relationen
     */
    public function findByUid($uid)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('uid', (int)$uid));
        $query->setLimit(1);

        $event = $query->execute()->getFirst();

        if ($event instanceof \Nkfire\RescueReports\Domain\Model\Event) {
            $event->getStations();
            $event->getVehicles();
        }

        return $event;
    }

    /**
     * Liefert alle Events (optional: mit Relationen)
     */
    public function findAllWithRelations(): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->setOrderings($this->getDefaultOrderings());

        return $query->execute();
    }

    /**
     * Suche mit optionalen Filtern (Datum & Limit)
     */
    public function search(string $searchWord = '', $dateFrom = null, $dateTo = null, int $limit = 0): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        $constraints = [];

        if (trim($searchWord) !== '') {
            $constraints[] = $query->logicalOr([
                $query->like('title', '%' . $searchWord . '%'),
                $query->like('description', '%' . $searchWord . '%'),
                $query->like('location', '%' . $searchWord . '%'),
                $query->like('types.title', '%' . $searchWord . '%'),
                $query->like('number', '%' . $searchWord . '%'),
            ]);
        }

        $dateConstraints = $this->buildDateConstraints($query, $dateFrom, $dateTo);
        if (!empty($dateConstraints)) {
            $constraints = array_merge($constraints, $dateConstraints);
        }

        if (!empty($constraints)) {
            $query->matching($query->logicalAnd($constraints));
        }

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        $query->setOrderings($this->getDefaultOrderings());

        return $query->execute();
    }

    /**
     * Suche gefiltert nach Station
     */
    public function searchByStation(
        int $stationUid,
        string $searchWord = '',
        $dateFrom = null,
        $dateTo = null,
        int $limit = 0
    ): QueryResultInterface {
        $uids = $this->findEventUidsByStation($stationUid, $dateFrom, $dateTo, $searchWord, $limit);

        return $this->findByUids($uids);
    }

    /**
     * Liefert Events gefiltert nach Datum & Limit
     */
    public function findFiltered($dateFrom = null, $dateTo = null, int $limit = 0, bool $excludeDisabledDetail = false): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        $constraints = $this->buildDateConstraints($query, $dateFrom, $dateTo);

        if ($excludeDisabledDetail) {
            $constraints[] = $query->equals('disableDetail', false);
        }

        if (!empty($constraints)) {
            $query->matching($query->logicalAnd($constraints));
        }

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        $query->setOrderings($this->getDefaultOrderings());

        return $query->execute();
    }

    /**
     * Liefert Events gefiltert nach Station, Datum & Limit
     */
    public function findFilteredByStation(int $stationUid, $dateFrom = null, $dateTo = null, int $limit = 0, bool $excludeDisabledDetail = false): QueryResultInterface
    {
        $uids = $this->findEventUidsByStation($stationUid, $dateFrom, $dateTo, '', $limit, $excludeDisabledDetail);

        return $this->findByUids($uids);
    }

    /**
     * Gemeinsame Sortierung
     */
    protected function getDefaultOrderings(): array
    {
        return [
            'start' => QueryInterface::ORDER_DESCENDING,
            'number' => QueryInterface::ORDER_DESCENDING,
            'uid' => QueryInterface::ORDER_DESCENDING,
        ];
    }

    /**
     * Baut Datums-Constraints für den Einsatzbeginn auf
     *
     * dateFrom => start ab 00:00:00 dieses Tages
     * dateTo   => start bis 23:59:59 dieses Tages
     */
    protected function buildDateConstraints($query, $dateFrom = null, $dateTo = null): array
    {
        $constraints = [];

        $fromDate = $this->convertToDateTime($dateFrom);
        if ($fromDate instanceof \DateTimeInterface) {
            $fromDate = (clone $fromDate)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $constraints[] = $query->greaterThanOrEqual('start', $fromDate);
        }

        $toDate = $this->convertToDateTime($dateTo);
        if ($toDate instanceof \DateTimeInterface) {
            $toDate = (clone $toDate)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $constraints[] = $query->lessThanOrEqual('start', $toDate);
        }

        return $constraints;
    }

    /**
     * Hilfsfunktion: String oder Timestamp -> DateTime
     */
    protected function convertToDateTime($value): ?\DateTime
    {
        if ($value instanceof \DateTime) {
            return clone $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return new \DateTime($value->format('Y-m-d H:i:s'));
        }

        if ($value === null || $value === '' || $value === '0') {
            return null;
        }

        if (is_numeric($value)) {
            return (new \DateTime())->setTimestamp((int)$value);
        }

        if (is_string($value) && strtotime($value) !== false) {
            return new \DateTime($value);
        }

        return null;
    }

    /**
     * Liefert Event-UIDs über die MM-Tabelle gefiltert nach Station.
     */
    protected function findEventUidsByStation(
        int $stationUid,
        $dateFrom = null,
        $dateTo = null,
        string $searchWord = '',
        int $limit = 0,
        bool $excludeDisabledDetail = false
    ): array {
        if ($stationUid <= 0) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');

        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->select('e.uid')
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->innerJoin(
                'e',
                'tx_rescuereports_event_station_mm',
                'mm',
                $queryBuilder->expr()->eq(
                    'mm.uid_local',
                    $queryBuilder->quoteIdentifier('e.uid')
                )
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'e.deleted',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'e.hidden',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'mm.uid_foreign',
                    $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT)
                )
            );

        if ($excludeDisabledDetail) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'e.disable_detail',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                )
            );
        }

        $fromDate = $this->convertToDateTime($dateFrom);
        if ($fromDate instanceof \DateTimeInterface) {
            $fromDate = (clone $fromDate)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte(
                    'e.start',
                    $queryBuilder->createNamedParameter($fromDate)
                )
            );
        }

        $toDate = $this->convertToDateTime($dateTo);
        if ($toDate instanceof \DateTimeInterface) {
            $toDate = (clone $toDate)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $queryBuilder->andWhere(
                $queryBuilder->expr()->lte(
                    'e.start',
                    $queryBuilder->createNamedParameter($toDate)
                )
            );
        }

        if (trim($searchWord) !== '') {
            $like = '%' . $queryBuilder->escapeLikeWildcards($searchWord) . '%';

            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->like(
                        'e.title',
                        $queryBuilder->createNamedParameter($like)
                    ),
                    $queryBuilder->expr()->like(
                        'e.description',
                        $queryBuilder->createNamedParameter($like)
                    ),
                    $queryBuilder->expr()->like(
                        'e.location',
                        $queryBuilder->createNamedParameter($like)
                    ),
                    $queryBuilder->expr()->like(
                        'e.number',
                        $queryBuilder->createNamedParameter($like)
                    )
                )
            );
        }

        $queryBuilder
            ->groupBy('e.uid')
            ->orderBy('e.start', 'DESC')
            ->addOrderBy('e.number', 'DESC')
            ->addOrderBy('e.uid', 'DESC');

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }

        $uids = $queryBuilder->executeQuery()->fetchFirstColumn();

        return array_map('intval', $uids ?: []);
    }

    /**
     * Baut aus einer UID-Liste ein Extbase-QueryResult.
     */
    protected function findByUids(array $uids): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        if ($uids === []) {
            $query->matching($query->equals('uid', 0));
            return $query->execute();
        }

        $query->matching($query->in('uid', $uids));
        $query->setOrderings($this->getDefaultOrderings());

        return $query->execute();
    }

    /**
     * Zählt die Einsätze einer Station innerhalb eines Jahres bis zum aktuellen Einsatzzeitpunkt.
     * Bei gleichem Startzeitpunkt entscheidet die UID.
     */
    public function countByStationAndYearUntil(\DateTime $date, int $stationUid, int $currentEventUid = 0): int
    {
        if ($stationUid <= 0) {
            return 0;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');

        $queryBuilder->getRestrictions()->removeAll();

        $yearStart = new \DateTime($date->format('Y-01-01 00:00:00'));

        $count = $queryBuilder
            ->count('e.uid')
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->innerJoin(
                'e',
                'tx_rescuereports_event_station_mm',
                'mm',
                $queryBuilder->expr()->eq(
                    'mm.uid_local',
                    $queryBuilder->quoteIdentifier('e.uid')
                )
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'e.deleted',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'e.hidden',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'mm.uid_foreign',
                    $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->gte(
                    'e.start',
                    $queryBuilder->createNamedParameter($yearStart->format('Y-m-d H:i:s'))
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->lt(
                        'e.start',
                        $queryBuilder->createNamedParameter($date->format('Y-m-d H:i:s'))
                    ),
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->eq(
                            'e.start',
                            $queryBuilder->createNamedParameter($date->format('Y-m-d H:i:s'))
                        ),
                        $queryBuilder->expr()->lte(
                            'e.uid',
                            $queryBuilder->createNamedParameter($currentEventUid, PDO::PARAM_INT)
                        )
                    )
                )
            )
            ->executeQuery()
            ->fetchOne();

        return (int)$count;
    }

    /**
     * Liefert Jahresstatistiken nach Kategorie.
     *
     * Optional: Filterung auf eine Ortsfeuerwehr (stationUid > 0).
     *
     * Rückgabe: [
     *   2025 => [
     *     'total' => 150,
     *     'categories' => [
     *       ['uid' => 1, 'title' => 'Brand', 'color' => '#e74c3c', 'count' => 45, 'percent' => 30.0],
     *       ...
     *     ],
     *   ],
     *   ...
     * ]
     */
    public function getYearlyStatistics(int $stationUid = 0, int $maxYears = 0): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');

        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->select('cat.uid AS cat_uid', 'cat.title AS cat_title', 'cat.color AS cat_color')
            ->addSelectLiteral(
                'YEAR(e.start) AS year',
                'COUNT(DISTINCT e.uid) AS cnt',
                'ROUND(AVG(TIMESTAMPDIFF(SECOND, e.start, e.end))) AS avg_dur_sec'
            )
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->leftJoin('e', 'tx_rescuereports_event_type_mm', 'tmm', 'e.uid = tmm.uid_local')
            ->leftJoin('tmm', 'tx_rescuereports_domain_model_type', 't', 'tmm.uid_foreign = t.uid')
            ->leftJoin('t', 'tx_rescuereports_domain_model_category', 'cat', 't.category = cat.uid')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('e.hidden', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->isNotNull('e.start')
            );

        if ($stationUid > 0) {
            $queryBuilder
                ->innerJoin('e', 'tx_rescuereports_event_station_mm', 'smm', 'e.uid = smm.uid_local')
                ->andWhere(
                    $queryBuilder->expr()->eq('smm.uid_foreign', $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT))
                );
        }

        $rows = $queryBuilder
            ->groupBy('year', 'cat.uid')
            ->orderBy('year', 'DESC')
            ->addOrderBy('cat.title', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        // Aufbau: year -> categories[] + total
        $raw = [];
        foreach ($rows as $row) {
            $year = (int)$row['year'];
            if (!isset($raw[$year])) {
                $raw[$year] = [];
            }
            $raw[$year][] = [
                'uid'   => (int)$row['cat_uid'],
                'title' => (string)($row['cat_title'] ?: '– ohne Kategorie –'),
                'color' => (string)($row['cat_color'] ?: '#95a5a6'),
                'count'       => (int)$row['cnt'],
                'avg_dur_sec' => isset($row['avg_dur_sec']) && $row['avg_dur_sec'] !== null ? (int)$row['avg_dur_sec'] : null,
            ];
        }

        // Einsatzarten je Jahr/Kategorie aus tatsächlich vorkommenden Datensätzen ermitteln
        $typesByYearAndCategory = $this->getTypeStatsByYearAndCategory($stationUid);

        // Gesamtzahl + Prozentwerte + SVG-Tortendiagramm berechnen
        $statistics = [];
        foreach ($raw as $year => $categories) {
            $total = array_sum(array_column($categories, 'count'));
            foreach ($categories as &$cat) {
                $cat['percent']     = $total > 0 ? round($cat['count'] / $total * 100, 1) : 0.0;
                $cat['avgDuration'] = $this->formatDurationSeconds($cat['avg_dur_sec'] ?? null);
                $cat['types']       = $typesByYearAndCategory[$year][$cat['uid']] ?? [];
                foreach ($cat['types'] as &$type) {
                    $type['percent'] = $cat['count'] > 0 ? round($type['count'] / $cat['count'] * 100, 1) : 0.0;
                }
                unset($type);
            }
            unset($cat);

            // SVG-Pfade für Tortendiagramm (Kreis 220×220, Mittelpunkt 110/110, Radius 100)
            $svgPaths = [];
            $cx = 110; $cy = 110; $r = 100;
            if (count($categories) === 1) {
                // Einzelkategorie: Vollkreis
                $svgPaths[] = [
                    'type'        => 'circle',
                    'color'       => $categories[0]['color'],
                    'title'       => $categories[0]['title'],
                    'count'       => $categories[0]['count'],
                    'percent'     => $categories[0]['percent'],
                    'categoryUid' => $categories[0]['uid'],
                ];
            } else {
                $startAngle = -M_PI / 2; // Start bei 12 Uhr
                foreach ($categories as $cat) {
                    $sliceAngle = $total > 0 ? ($cat['count'] / $total * 2 * M_PI) : 0;
                    $endAngle   = $startAngle + $sliceAngle;
                    $x1 = round($cx + $r * cos($startAngle), 3);
                    $y1 = round($cy + $r * sin($startAngle), 3);
                    $x2 = round($cx + $r * cos($endAngle), 3);
                    $y2 = round($cy + $r * sin($endAngle), 3);
                    $largeArc   = $sliceAngle > M_PI ? 1 : 0;
                    $svgPaths[] = [
                        'type'        => 'path',
                        'color'       => $cat['color'],
                        'd'           => 'M ' . $cx . ' ' . $cy . ' L ' . $x1 . ' ' . $y1
                                         . ' A ' . $r . ' ' . $r . ' 0 ' . $largeArc . ' 1 ' . $x2 . ' ' . $y2 . ' Z',
                        'title'       => $cat['title'],
                        'count'       => $cat['count'],
                        'percent'     => $cat['percent'],
                        'categoryUid' => $cat['uid'],
                    ];
                    $startAngle = $endAngle;
                }
            }

            $statistics[$year] = [
                'total'             => $total,
                'categories'        => $categories,
                'svgPaths'          => $svgPaths,
            ];
        }

        // Gesamtdauer pro Jahr separat berechnen (kein Type-JOIN → kein double-counting)
        $yearlyTotals = $this->getYearlyTotalDurations($stationUid);
        foreach ($yearlyTotals as $year => $totalSec) {
            if (isset($statistics[$year])) {
                $statistics[$year]['yearTotalDuration'] = $this->formatDurationSeconds($totalSec);
            }
        }

        // Vorjahresvergleich berechnen
        foreach (array_keys($statistics) as $year) {
            $prevYear = $year - 1;
            if (!isset($statistics[$prevYear])) {
                continue;
            }
            $current  = $statistics[$year]['total'];
            $previous = $statistics[$prevYear]['total'];
            if ($previous <= 0) {
                continue;
            }
            $diff    = $current - $previous;
            $percent = round(abs($diff) / $previous * 100, 1);
            $percentFormatted = str_replace('.', ',', (string)$percent);
            if ($diff > 0) {
                $label = sprintf('+%s %% mehr als %d (%d Einsätze)', $percentFormatted, $prevYear, $previous);
            } elseif ($diff < 0) {
                $label = sprintf('−%s %% weniger als %d (%d Einsätze)', $percentFormatted, $prevYear, $previous);
            } else {
                $label = sprintf('gleich viele Einsätze wie %d', $prevYear);
            }
            $statistics[$year]['yearCompare'] = $label;
        }

        // Auf die gewünschte Anzahl Jahre begrenzen (nach Vorjahresvergleich, damit die Anzeige korrekt ist)
        if ($maxYears > 0) {
            $statistics = array_slice($statistics, 0, $maxYears, true);
        }

        return $statistics;
    }

    /**
     * Monatliche Einsatzzahlen für das Balkendiagramm (Mehrjahresvergleich).
     *
     * @return array{
     *   years:int[],
     *   monthNames:string[],
     *   monthCounts:array<int,array<int,int>>,
     *   maxCount:int,
     *   mobileRows:array<int,array{month:string,values:array<int,array{year:int,count:int,percent:float,color:string}>>>,
     *   svgBarChart:array
     * }
     */
    public function getMonthlyStatistics(int $stationUid = 0, int $maxYears = 0): array
    {
        $monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $yearColors = ['#3498db', '#e67e22', '#2ecc71', '#9b59b6', '#e74c3c', '#1abc9c', '#f39c12', '#34495e'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');

        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->addSelectLiteral(
                'YEAR(e.start) AS year',
                'MONTH(e.start) AS month',
                'COUNT(DISTINCT e.uid) AS cnt'
            )
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('e.hidden', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->isNotNull('e.start')
            );

        if ($stationUid > 0) {
            $queryBuilder
                ->innerJoin('e', 'tx_rescuereports_event_station_mm', 'smm', 'e.uid = smm.uid_local')
                ->andWhere(
                    $queryBuilder->expr()->eq('smm.uid_foreign', $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT))
                );
        }

        $rows = $queryBuilder
            ->groupBy('year', 'month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        // Rohdaten → [year => [month => count]]
        $raw = [];
        foreach ($rows as $row) {
            $year  = (int)$row['year'];
            $month = (int)$row['month'];
            if (!isset($raw[$year])) {
                $raw[$year] = [];
            }
            $raw[$year][$month] = (int)$row['cnt'];
        }

        if ($maxYears > 0) {
            $raw = array_slice($raw, 0, $maxYears, true);
        }

        $years  = array_keys($raw);
        $maxCnt = 0;
        foreach ($raw as $months) {
            if ($months) {
                $maxCnt = max($maxCnt, max($months));
            }
        }

        $mobileRows = [];
        for ($m = 1; $m <= 12; $m++) {
            $values = [];
            foreach ($years as $yi => $year) {
                $count = (int)($raw[$year][$m] ?? 0);
                $values[] = [
                    'year'    => $year,
                    'count'   => $count,
                    'percent' => $maxCnt > 0 ? round($count / $maxCnt * 100, 1) : 0.0,
                    'color'   => $yearColors[$yi % count($yearColors)],
                ];
            }
            $mobileRows[] = [
                'month'  => $monthNames[$m - 1],
                'values' => $values,
            ];
        }

        return [
            'years'       => $years,
            'monthNames'  => $monthNames,
            'monthCounts' => $raw,
            'maxCount'    => $maxCnt,
            'mobileRows'  => $mobileRows,
            'svgBarChart' => $this->buildMonthlyBarChartSvg($raw, $years, $maxCnt),
        ];
    }

    /**
     * Berechnet die SVG-Daten für das gruppierte Balkendiagramm (Monat × Jahr).
     *
     * @param array<int,array<int,int>> $raw    [year => [month => count]]
     * @param int[]                     $years  Jahresliste (absteigende Reihenfolge)
     * @param int                       $maxCnt Höchstwert über alle Monate/Jahre
     * @return array
     */
    private function buildMonthlyBarChartSvg(array $raw, array $years, int $maxCnt): array
    {
        $W = 700; $H = 300;
        $mL = 45; $mB = 55; $mT = 15; $mR = 15;
        $plotW = $W - $mL - $mR;  // 640
        $plotH = $H - $mB - $mT;  // 230

        $yearColors = ['#3498db', '#e67e22', '#2ecc71', '#9b59b6', '#e74c3c', '#1abc9c', '#f39c12', '#34495e'];
        $monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $nYears     = count($years);
        $groupW     = $plotW / 12;
        $barW       = $nYears > 0 ? max(3.0, ($groupW - 4) / $nYears - 1) : $groupW - 4;
        $yScale     = $maxCnt > 0 ? $plotH / $maxCnt : 1.0;

        // Gitterlinien (4–5 Stufen)
        $step = $maxCnt > 0 ? (int)ceil($maxCnt / 5) : 1;
        $gridLines = [];
        for ($v = $step; $v <= $maxCnt; $v += $step) {
            $y = round($mT + $plotH - $v * $yScale, 2);
            $gridLines[] = [
                'x1'     => $mL,
                'y1'     => $y,
                'x2'     => $mL + $plotW,
                'y2'     => $y,
                'label'  => $v,
                'labelX' => $mL - 4,
                'labelY' => $y + 4,
            ];
        }

        // Balken
        $bars = [];
        foreach ($years as $yi => $year) {
            $color = $yearColors[$yi % count($yearColors)];
            for ($m = 1; $m <= 12; $m++) {
                $cnt = $raw[$year][$m] ?? 0;
                $bH  = round($cnt * $yScale, 2);
                $x   = round($mL + ($m - 1) * $groupW + 2 + $yi * ($barW + 1), 2);
                $y   = round($mT + $plotH - $bH, 2);
                $bars[] = [
                    'x'       => $x,
                    'y'       => $y,
                    'width'   => round($barW, 2),
                    'height'  => max(0.5, $bH),
                    'color'   => $color,
                    'tooltip' => $monthNames[$m - 1] . ' ' . $year . ': ' . $cnt,
                ];
            }
        }

        // Monatsbeschriftungen
        $monthLabels = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthLabels[] = [
                'x'    => round($mL + ($m - 1) * $groupW + $groupW / 2, 2),
                'y'    => $mT + $plotH + 14,
                'text' => $monthNames[$m - 1],
            ];
        }

        // Legende (zentriert unterhalb der Monatsnamen)
        $legendTotalW = $nYears * 65;
        $legendStartX = $mL + ($plotW - $legendTotalW) / 2;
        $legend = [];
        foreach ($years as $yi => $year) {
            $lx = round($legendStartX + $yi * 65, 2);
            $ly = $H - 14;
            $legend[] = [
                'rx'    => $lx,
                'ry'    => $ly - 9,
                'color' => $yearColors[$yi % count($yearColors)],
                'tx'    => $lx + 13,
                'ty'    => $ly,
                'label' => (string)$year,
            ];
        }

        return [
            'viewBox'     => "0 0 $W $H",
            'axisLeft'    => $mL,
            'axisBottom'  => $mT + $plotH,
            'axisRight'   => $mL + $plotW,
            'gridLines'   => $gridLines,
            'monthLabels' => $monthLabels,
            'bars'        => $bars,
            'legend'      => $legend,
        ];
    }

    /**
     * Liefert die Summe der Einsatzdauern (in Sekunden) je Jahr.
     * Kein JOIN auf die Typ-MM-Tabelle, damit Events mit mehreren Typen nicht mehrfach gezählt werden.
     *
     * @return array<int, int>  [$year => $totalSeconds]
     */
    private function getYearlyTotalDurations(int $stationUid = 0): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');

        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->addSelectLiteral(
                'YEAR(e.start) AS year',
                'SUM(TIMESTAMPDIFF(SECOND, e.start, e.end)) AS total_sec'
            )
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('e.hidden', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->isNotNull('e.start'),
                $queryBuilder->expr()->isNotNull('e.end')
            );

        if ($stationUid > 0) {
            $queryBuilder
                ->innerJoin('e', 'tx_rescuereports_event_station_mm', 'smm', 'e.uid = smm.uid_local')
                ->andWhere(
                    $queryBuilder->expr()->eq('smm.uid_foreign', $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT))
                );
        }

        $rows = $queryBuilder
            ->groupBy('year')
            ->orderBy('year', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $year = (int)$row['year'];
            $result[$year] = $row['total_sec'] !== null ? (int)$row['total_sec'] : null;
        }

        return $result;
    }

    /**
     * Liefert alle Jahre (absteigend), in denen Einsätze vorhanden sind.
     * Optional gefiltert nach Ortsfeuerwehr.
     *
     * @return int[]
     */
    public function getAvailableYears(int $stationUid = 0): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');

        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->addSelectLiteral('YEAR(e.start) AS year')
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('e.hidden', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->isNotNull('e.start')
            );

        if ($stationUid > 0) {
            $queryBuilder
                ->innerJoin('e', 'tx_rescuereports_event_station_mm', 'smm', 'e.uid = smm.uid_local')
                ->andWhere(
                    $queryBuilder->expr()->eq('smm.uid_foreign', $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT))
                );
        }

        $rows = $queryBuilder
            ->groupBy('year')
            ->orderBy('year', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): int => (int)$row['year'], $rows);
    }

    private function formatDurationSeconds(?int $seconds): string
    {
        if ($seconds === null || $seconds <= 0) {
            return '—';
        }
        $hours   = (int)($seconds / 3600);
        $minutes = (int)(($seconds % 3600) / 60);
        return $hours > 0
            ? sprintf('%d Std. %02d Min.', $hours, $minutes)
            : sprintf('%d Min.', $minutes);
    }

    /**
     * Liefert pro Jahr und Kategorie nur die Einsatzarten, die in den gefilterten Datensätzen vorkommen.
     *
     * @return array<int,array<int,array<int,array{title:string,count:int}>>> [year => [catUid => [['title' => string, 'count' => int], ...]]]
     */
    private function getTypeStatsByYearAndCategory(int $stationUid = 0): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');
        $qb->getRestrictions()->removeAll();

        $qb->select('cat.uid AS cat_uid', 't.title AS type_title')
            ->addSelectLiteral('YEAR(e.start) AS year', 'COUNT(DISTINCT e.uid) AS cnt')
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->innerJoin('e', 'tx_rescuereports_event_type_mm', 'tmm', 'e.uid = tmm.uid_local')
            ->innerJoin('tmm', 'tx_rescuereports_domain_model_type', 't', 'tmm.uid_foreign = t.uid')
            ->leftJoin('t', 'tx_rescuereports_domain_model_category', 'cat', 't.category = cat.uid')
            ->where(
                $qb->expr()->eq('e.deleted', $qb->createNamedParameter(0, \PDO::PARAM_INT)),
                $qb->expr()->eq('e.hidden', $qb->createNamedParameter(0, \PDO::PARAM_INT)),
                $qb->expr()->eq('t.deleted', $qb->createNamedParameter(0, \PDO::PARAM_INT)),
                $qb->expr()->eq('t.hidden', $qb->createNamedParameter(0, \PDO::PARAM_INT)),
                $qb->expr()->isNotNull('e.start')
            )
            ->groupBy('year', 'cat.uid', 't.uid', 't.title')
            ->orderBy('year', 'DESC')
            ->addOrderBy('cat.title', 'ASC')
            ->addOrderBy('cnt', 'DESC')
            ->addOrderBy('t.title', 'ASC');

        if ($stationUid > 0) {
            $qb->innerJoin('e', 'tx_rescuereports_event_station_mm', 'smm', 'e.uid = smm.uid_local')
                ->andWhere(
                    $qb->expr()->eq('smm.uid_foreign', $qb->createNamedParameter($stationUid, \PDO::PARAM_INT))
                );
        }

        // query after optional station filter
        $rows = $qb->executeQuery()->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $year = (int)$row['year'];
            $catUid = (int)$row['cat_uid'];
            $count = (int)$row['cnt'];
            if ($year <= 0 || $catUid <= 0 || $count <= 0) {
                continue;
            }
            $result[$year][$catUid][] = [
                'title' => (string)$row['type_title'],
                'count' => $count,
            ];
        }

        return $result;
    }
}
