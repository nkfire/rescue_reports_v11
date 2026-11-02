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
    public function getYearlyStatistics(
        int $stationUid = 0,
        int $maxYears = 0,
        bool $includeLongestOperation = false,
        bool $includeShortestOperation = false
    ): array
    {
        $rows = $this->getEffectiveStatisticsRows($stationUid);
        $raw = [];
        $typeCountsByYearAndCategory = [];
        $durationEventKeysByYearAndCategory = [];

        foreach ($rows as $row) {
            $year = (int)($row['year'] ?? 0);
            if ($year <= 0) {
                continue;
            }

            $useKeywordEscalation = (int)($row['enable_keyword_escalation'] ?? 0) === 1
                && (int)($row['keyword_uid'] ?? 0) > 0;

            $categoryUid = (int)($useKeywordEscalation ? ($row['keyword_cat_uid'] ?? 0) : ($row['type_cat_uid'] ?? 0));
            $categoryTitle = trim((string)($useKeywordEscalation ? ($row['keyword_cat_title'] ?? '') : ($row['type_cat_title'] ?? '')));
            $categoryColor = trim((string)($useKeywordEscalation ? ($row['keyword_cat_color'] ?? '') : ($row['type_cat_color'] ?? '')));
            $effectiveTypeTitle = trim((string)($useKeywordEscalation ? ($row['keyword_title'] ?? '') : ($row['type_title'] ?? '')));
            $eventUid = (int)($row['uid'] ?? 0);
            $durationSeconds = max(0, (int)($row['duration_sec'] ?? 0));

            if (!isset($raw[$year][$categoryUid])) {
                $raw[$year][$categoryUid] = [
                    'uid' => $categoryUid,
                    'title' => $categoryTitle !== '' ? $categoryTitle : '– ohne Kategorie –',
                    'color' => $categoryColor !== '' ? $categoryColor : '#95a5a6',
                    'count' => 0,
                    'total_dur_sec' => 0,
                ];
            }

            $raw[$year][$categoryUid]['count']++;

            if (
                $eventUid > 0
                && $durationSeconds > 0
                && !isset($durationEventKeysByYearAndCategory[$year][$categoryUid][$eventUid])
            ) {
                $raw[$year][$categoryUid]['total_dur_sec'] += $durationSeconds;
                $durationEventKeysByYearAndCategory[$year][$categoryUid][$eventUid] = true;
            }

            if ($effectiveTypeTitle !== '') {
                $typeCountsByYearAndCategory[$year][$categoryUid][$effectiveTypeTitle] =
                    ($typeCountsByYearAndCategory[$year][$categoryUid][$effectiveTypeTitle] ?? 0) + 1;
            }
        }

        $typesByYearAndCategory = [];

        foreach ($raw as $year => $categories) {
            uasort(
                $categories,
                static fn(array $left, array $right): int => strcasecmp($left['title'], $right['title'])
            );
            $raw[$year] = array_values($categories);
        }

        $statistics = [];
        foreach ($raw as $year => $categories) {
            $total = array_sum(array_column($categories, 'count'));
            foreach ($categories as &$cat) {
                $cat['percent']     = $total > 0 ? round($cat['count'] / $total * 100, 1) : 0.0;
                $cat['totalDuration'] = $this->formatDurationSeconds($cat['total_dur_sec'] ?? null);
                $cat['avgDuration'] = $cat['count'] > 0
                    ? $this->formatDurationSeconds((int)(($cat['total_dur_sec'] ?? 0) / $cat['count']))
                    : '—';
                $cat['types']       = $this->buildTypeStatistics(
                    $typeCountsByYearAndCategory[$year][$cat['uid']] ?? [],
                    $cat['count']
                );
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
                'yearTotalDuration' => '—',
            ];
        }

        // Gesamtdauer pro Jahr separat berechnen (kein Type-JOIN → kein double-counting)
        $yearlyTotals = $this->getYearlyTotalDurations($stationUid);
        foreach ($yearlyTotals as $year => $totalSec) {
            if (isset($statistics[$year])) {
                $statistics[$year]['yearTotalDuration'] = $this->formatDurationSeconds($totalSec);
            }
        }

        if ($includeLongestOperation || $includeShortestOperation) {
            foreach ($this->getYearlyDurationExtremes($stationUid) as $year => $extremes) {
                if (!isset($statistics[$year])) {
                    continue;
                }

                $statistics[$year]['durationExtremes'] = [];
                if ($includeLongestOperation && isset($extremes['longest'])) {
                    $statistics[$year]['durationExtremes']['longest'] = $extremes['longest'];
                }
                if ($includeShortestOperation && isset($extremes['shortest'])) {
                    $statistics[$year]['durationExtremes']['shortest'] = $extremes['shortest'];
                }
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
                'SUM(CASE WHEN e.end IS NOT NULL AND e.end >= e.start THEN TIMESTAMPDIFF(SECOND, e.start, e.end) ELSE 0 END) AS total_sec'
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
     * @return array<int,array{longest?:array<string,mixed>,shortest?:array<string,mixed>}>
     */
    private function getYearlyDurationExtremes(int $stationUid = 0): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->select('e.uid', 'e.title', 'e.start', 'e.end')
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('e.hidden', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->isNotNull('e.start'),
                $queryBuilder->expr()->isNotNull('e.end')
            )
            ->orderBy('e.start', 'ASC')
            ->addOrderBy('e.uid', 'ASC');

        if ($stationUid > 0) {
            $queryBuilder
                ->innerJoin('e', 'tx_rescuereports_event_station_mm', 'smm', 'e.uid = smm.uid_local')
                ->andWhere(
                    $queryBuilder->expr()->eq('smm.uid_foreign', $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT))
                );
        }

        $extremes = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $startTimestamp = $this->normalizeTimestamp($row['start'] ?? null);
            $durationSeconds = $this->calculateDurationSeconds($row['start'] ?? null, $row['end'] ?? null);
            if ($startTimestamp === null || $durationSeconds === null || $durationSeconds <= 0) {
                continue;
            }

            $year = (int)date('Y', $startTimestamp);
            $entry = [
                'uid' => (int)($row['uid'] ?? 0),
                'title' => trim((string)($row['title'] ?? '')) ?: 'Ohne Titel',
                'date' => date('d.m.Y', $startTimestamp),
                'duration' => $this->formatDurationSeconds($durationSeconds),
                'durationSeconds' => $durationSeconds,
            ];

            if (!isset($extremes[$year]['longest']) || $durationSeconds > $extremes[$year]['longest']['durationSeconds']) {
                $extremes[$year]['longest'] = $entry;
            }
            if (!isset($extremes[$year]['shortest']) || $durationSeconds < $extremes[$year]['shortest']['durationSeconds']) {
                $extremes[$year]['shortest'] = $entry;
            }
        }

        return $extremes;
    }

    /**
     * @param int[] $stationUids
     * @return array{year:int,stations:array<int,array{count:int,durationSeconds:int,duration:string}>,totalDurationSeconds:int,totalDuration:string}
     */
    public function getStationYearOverview(array $stationUids, int $year): array
    {
        $stationUids = array_values(array_filter(
            array_map('intval', $stationUids),
            static function (int $stationUid): bool {
                return $stationUid > 0;
            }
        ));

        $stations = [];
        foreach ($stationUids as $stationUid) {
            $stations[$stationUid] = ['count' => 0, 'durationSeconds' => 0, 'duration' => '—'];
        }

        if ($stations === [] || $year <= 0) {
            return [
                'year' => $year,
                'stations' => $stations,
                'totalDurationSeconds' => 0,
                'totalDuration' => '—',
            ];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');
        $queryBuilder->getRestrictions()->removeAll();
        $yearStart = sprintf('%d-01-01 00:00:00', $year);
        $nextYearStart = sprintf('%d-01-01 00:00:00', $year + 1);

        $queryBuilder
            ->select('e.uid AS event_uid', 'e.start', 'e.end', 'smm.uid_foreign AS station_uid')
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->innerJoin('e', 'tx_rescuereports_event_station_mm', 'smm', 'e.uid = smm.uid_local')
            ->where(
                $queryBuilder->expr()->eq('e.deleted', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('e.hidden', $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)),
                $queryBuilder->expr()->gte('e.start', $queryBuilder->createNamedParameter($yearStart)),
                $queryBuilder->expr()->lt('e.start', $queryBuilder->createNamedParameter($nextYearStart))
            );

        $stationConstraints = [];
        foreach ($stationUids as $stationUid) {
            $stationConstraints[] = $queryBuilder->expr()->eq(
                'smm.uid_foreign',
                $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT)
            );
        }
        $queryBuilder->andWhere($queryBuilder->expr()->or(...$stationConstraints));

        $seenEvents = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $stationUid = (int)($row['station_uid'] ?? 0);
            $eventUid = (int)($row['event_uid'] ?? 0);
            if ($stationUid <= 0 || $eventUid <= 0 || !isset($stations[$stationUid]) || isset($seenEvents[$stationUid][$eventUid])) {
                continue;
            }

            $seenEvents[$stationUid][$eventUid] = true;
            $stations[$stationUid]['count']++;
            $durationSeconds = $this->calculateDurationSeconds($row['start'] ?? null, $row['end'] ?? null);
            if ($durationSeconds !== null) {
                $stations[$stationUid]['durationSeconds'] += $durationSeconds;
            }
        }

        $totalDurationSeconds = 0;
        foreach ($stations as &$station) {
            $station['duration'] = $this->formatDurationSeconds($station['durationSeconds']);
            $totalDurationSeconds += $station['durationSeconds'];
        }
        unset($station);

        return [
            'year' => $year,
            'stations' => $stations,
            'totalDurationSeconds' => $totalDurationSeconds,
            'totalDuration' => $this->formatDurationSeconds($totalDurationSeconds),
        ];
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

    private function calculateDurationSeconds($startValue, $endValue): ?int
    {
        $startTimestamp = $this->normalizeTimestamp($startValue);
        $endTimestamp = $this->normalizeTimestamp($endValue);

        if ($startTimestamp === null || $endTimestamp === null || $endTimestamp < $startTimestamp) {
            return null;
        }

        return $endTimestamp - $startTimestamp;
    }

    private function normalizeTimestamp($value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }

    /**
     * Lädt die Rohdaten für die Statistik und liefert pro Einsatz genau einen Typ-Zweig:
     * bei aktivierter Stichworterhöhung den Escalation-Typ, sonst den normalen Alarmtyp.
     */
    private function getEffectiveStatisticsRows(int $stationUid = 0): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_event');

        $qb->getRestrictions()->removeAll();

        $qb->select(
            'e.uid',
            'e.start',
            'e.end',
            'e.enable_keyword_escalation',
            'type_t.uid AS type_uid',
            'type_t.title AS type_title',
            'type_cat.uid AS type_cat_uid',
            'type_cat.title AS type_cat_title',
            'type_cat.color AS type_cat_color',
            'keyword_t.uid AS keyword_uid',
            'keyword_t.title AS keyword_title',
            'keyword_cat.uid AS keyword_cat_uid',
            'keyword_cat.title AS keyword_cat_title',
            'keyword_cat.color AS keyword_cat_color'
        )
            ->addSelectLiteral(
                'YEAR(e.start) AS year',
                'CASE WHEN e.end IS NOT NULL AND e.end >= e.start THEN TIMESTAMPDIFF(SECOND, e.start, e.end) ELSE 0 END AS duration_sec'
            )
            ->from('tx_rescuereports_domain_model_event', 'e')
            ->leftJoin('e', 'tx_rescuereports_event_type_mm', 'tmm', 'e.uid = tmm.uid_local')
            ->leftJoin('tmm', 'tx_rescuereports_domain_model_type', 'type_t', 'tmm.uid_foreign = type_t.uid')
            ->leftJoin('type_t', 'tx_rescuereports_domain_model_category', 'type_cat', 'type_t.category = type_cat.uid')
            ->leftJoin('e', 'tx_rescuereports_event_keyword_escalation_mm', 'kmm', 'e.uid = kmm.uid_local')
            ->leftJoin('kmm', 'tx_rescuereports_domain_model_type', 'keyword_t', 'kmm.uid_foreign = keyword_t.uid')
            ->leftJoin('keyword_t', 'tx_rescuereports_domain_model_category', 'keyword_cat', 'keyword_t.category = keyword_cat.uid')
            ->where(
                $qb->expr()->eq('e.deleted', $qb->createNamedParameter(0, PDO::PARAM_INT)),
                $qb->expr()->eq('e.hidden', $qb->createNamedParameter(0, PDO::PARAM_INT)),
                $qb->expr()->isNotNull('e.start')
            )
            ->orderBy('year', 'DESC')
            ->addOrderBy('e.uid', 'DESC');

        $typeVisibilityConstraint = $qb->expr()->or(
            $qb->expr()->isNull('type_t.uid'),
            $qb->expr()->and(
                $qb->expr()->eq('type_t.deleted', $qb->createNamedParameter(0, PDO::PARAM_INT)),
                $qb->expr()->eq('type_t.hidden', $qb->createNamedParameter(0, PDO::PARAM_INT))
            )
        );
        $keywordVisibilityConstraint = $qb->expr()->or(
            $qb->expr()->isNull('keyword_t.uid'),
            $qb->expr()->and(
                $qb->expr()->eq('keyword_t.deleted', $qb->createNamedParameter(0, PDO::PARAM_INT)),
                $qb->expr()->eq('keyword_t.hidden', $qb->createNamedParameter(0, PDO::PARAM_INT))
            )
        );

        $qb->andWhere($typeVisibilityConstraint, $keywordVisibilityConstraint);

        if ($stationUid > 0) {
            $qb->innerJoin('e', 'tx_rescuereports_event_station_mm', 'smm', 'e.uid = smm.uid_local')
                ->andWhere(
                    $qb->expr()->eq('smm.uid_foreign', $qb->createNamedParameter($stationUid, PDO::PARAM_INT))
                );
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param array<string,int> $typeCounts
     * @return array<int,array{title:string,count:int,percent:float}>
     */
    private function buildTypeStatistics(array $typeCounts, int $categoryCount): array
    {
        if ($typeCounts === []) {
            return [];
        }

        arsort($typeCounts);

        $result = [];
        foreach ($typeCounts as $title => $count) {
            $result[] = [
                'title' => $title,
                'count' => $count,
                'percent' => $categoryCount > 0 ? round($count / $categoryCount * 100, 1) : 0.0,
            ];
        }

        usort(
            $result,
            static function (array $left, array $right): int {
                $countCompare = $right['count'] <=> $left['count'];
                if ($countCompare !== 0) {
                    return $countCompare;
                }

                return strcasecmp($left['title'], $right['title']);
            }
        );

        return $result;
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
