<?php
namespace Nkfire\RescueReports\Controller;

use Nkfire\RescueReports\Domain\Model\Event;
use Nkfire\RescueReports\Domain\Repository\EventRepository;
use Nkfire\RescueReports\Domain\Repository\StationRepository;
use Nkfire\RescueReports\PageTitle\EventPageTitleProvider;
use Nkfire\RescueReports\Utility\CompactListBuilder;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\MetaTag\MetaTagManagerRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

class EventController extends ActionController
{
    protected EventRepository $eventRepository;
    protected StationRepository $stationRepository;
    protected bool $excludeDisabledDetail = false;

    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function injectStationRepository(StationRepository $stationRepository): void
    {
        $this->stationRepository = $stationRepository;
    }

    /**
     * Liste aller Einsätze (mit optionalen FlexForm-Filtern)
     */
    public function listAction(
        ?string $searchWord = null,
        ?string $station = null,
        ?string $year = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): ResponseInterface {
        $maxCount = (int)($this->settings['maxCount'] ?? 0);
        $dateFromValue = $this->settings['dateFrom'] ?? null;
        $dateToValue   = $this->settings['dateTo'] ?? null;
        $enableSearch = (bool)($this->settings['enableSearch'] ?? false);
        $templateVariant     = $this->normalizeTemplateVariant((string)($this->settings['templateVariant'] ?? 'bootstrap'));
        $showStatistics      = (bool)($this->settings['showStatistics'] ?? false);
        $statisticsPosition  = (string)($this->settings['statisticsPosition'] ?? 'below');
        $statisticsYears     = (int)($this->settings['statisticsYears'] ?? 0);
        $showLongestOperation = (bool)($this->settings['showLongestOperation'] ?? false);
        $showShortestOperation = (bool)($this->settings['showShortestOperation'] ?? false);
        $enableYearFilter    = (bool)($this->settings['enableYearFilter'] ?? false);
        $enableDateFilter    = (bool)($this->settings['enableDateFilter'] ?? false);
        $showStationFilter   = (bool)($this->settings['showStationFilter'] ?? true);
        $showMapView         = (bool)($this->settings['showMapView'] ?? false);
        $mapPosition         = (string)($this->settings['mapPosition'] ?? 'below');
        $yearFilterDefault   = (string)($this->settings['yearFilterDefault'] ?? 'current');
        // $year === null  → erster Aufruf (kein Submit) → Standardauswahl aus Backend
        // $year === '0'   → Nutzer hat explizit „Alle Jahre" gewählt → 0 behalten
        // yearFilterDefault gilt immer; enableYearFilter steuert nur die UI-Anzeige des Filters
        $selectedYear = ($year === null)
            ? ($yearFilterDefault === 'all' ? 0 : (int)date('Y'))
            : (int)($year ?? 0);

        $hasExplicitDateFilter = $enableDateFilter
            && (($dateFrom !== null && $dateFrom !== '') || ($dateTo !== null && $dateTo !== ''));

        // Request-Datumswerte überschreiben FlexForm-Einstellung wenn Datumsfilter aktiv
        if ($enableDateFilter) {
            if ($dateFrom !== null && $dateFrom !== '') {
                $dateFromValue = $dateFrom;
            }
            if ($dateTo !== null && $dateTo !== '') {
                $dateToValue = $dateTo;
            }
        }

        // DateTime-Objekte + HTML-Input-Strings (YYYY-MM-DD) für das Template
        $dateFromDt  = $this->createDateTimeFromFlexFormValue($dateFromValue);
        $dateToDt    = $this->createDateTimeFromFlexFormValue($dateToValue);
        $dateFromStr = $dateFromDt instanceof \DateTime ? $dateFromDt->format('Y-m-d') : '';
        $dateToStr   = $dateToDt instanceof \DateTime   ? $dateToDt->format('Y-m-d')   : '';

        $detailPageUid = $this->normalizeDetailPageUid($this->settings['detailPageUid'] ?? null);
        $listPageUid   = $this->normalizeDetailPageUid($this->settings['listPageUid'] ?? null);
        $widgetTitle   = trim((string)($this->settings['widgetTitle'] ?? ''));

        $viewSettings = $this->normalizeCardImageSettings($this->settings);

        $defaultStationUid = (int)($this->settings['defaultStation'] ?? 0);
        $selectedStationUid = $this->normalizeRecordUid($station);
        $activeStationUid = $selectedStationUid > 0 ? $selectedStationUid : $defaultStationUid;

        if ($activeStationUid === 0) {
            $firstStation = $this->stationRepository->findPrimaryBrigadeStations()->getFirst();
            if ($firstStation) {
                $activeStationUid = (int)$firstStation->getUid();
            }
        }

        $activeStationName = '';
        if ($activeStationUid > 0) {
            $activeStation = $this->stationRepository->findByUid($activeStationUid);
            if ($activeStation) {
                $activeStationName = $activeStation->getName();
            }
        }

        $searchWord = trim((string)($searchWord ?? ''));
        $submittedSearchWord = $searchWord;
        $searchDateRange = $this->extractDateRangeFromSearchTerm($searchWord);

        $dateFrom = $dateFromValue;
        $dateTo = $dateToValue;

        // Explicit date inputs are more specific than the default year selection.
        if ($hasExplicitDateFilter) {
            $dateFrom = $dateFromValue;
            $dateTo = $dateToValue;
        // Jahresauswahl überschreibt FlexForm-Datumsbereich (unabhängig von enableYearFilter)
        } elseif ($selectedYear > 0) {
            $dateFrom = $selectedYear . '-01-01';
            $dateTo   = $selectedYear . '-12-31';
        } elseif ($yearFilterDefault === 'all' || $enableYearFilter) {
            // "Alle Jahre" als Standard oder Jahresfilter aktiv → FlexForm-Datumseinschränkungen aufheben
            $dateFrom = null;
            $dateTo   = null;
        }

        if ($searchDateRange !== null) {
            // Keep the page's configured year/date scope and narrow it by the search date.
            if (!$hasExplicitDateFilter) {
                $currentDateFrom = $this->createDateTimeFromFlexFormValue($dateFrom);
                $currentDateTo = $this->createDateTimeFromFlexFormValue($dateTo);
                $searchDateFrom = new \DateTime($searchDateRange['from']);
                $searchDateTo = new \DateTime($searchDateRange['to']);

                $dateFrom = ($currentDateFrom instanceof \DateTime && $currentDateFrom > $searchDateFrom)
                    ? $currentDateFrom->format('Y-m-d')
                    : $searchDateRange['from'];
                $dateTo = ($currentDateTo instanceof \DateTime && $currentDateTo < $searchDateTo)
                    ? $currentDateTo->format('Y-m-d')
                    : $searchDateRange['to'];
            }
            $searchWord = '';
        }

        $availableYears = $enableYearFilter
            ? $this->eventRepository->getAvailableYears($activeStationUid)
            : [];

        if ($activeStationUid > 0) {
            if ($enableSearch && $searchWord !== '') {
                $events = $this->eventRepository->searchByStation(
                    $activeStationUid,
                    $searchWord,
                    $dateFrom,
                    $dateTo,
                    $maxCount
                );
            } else {
                $events = $this->eventRepository->findFilteredByStation(
                    $activeStationUid,
                    $dateFrom,
                    $dateTo,
                    $maxCount,
                    $this->excludeDisabledDetail
                );
            }
        } else {
            if ($enableSearch && $searchWord !== '') {
                $events = $this->eventRepository->search($searchWord, $dateFrom, $dateTo, $maxCount);
            } else {
                $events = $this->eventRepository->findFiltered($dateFrom, $dateTo, $maxCount, $this->excludeDisabledDetail);
            }
        }

        $eventItems = $this->buildEventItemsForStations($events, $activeStationUid);
        $hasSearchQuery = $enableSearch && ($submittedSearchWord !== '' || $hasExplicitDateFilter);
        $searchResultContext = '';
        if ($submittedSearchWord !== '') {
            $searchResultContext = 'für „' . $submittedSearchWord . '“';
        } elseif ($hasExplicitDateFilter) {
            $from = $dateFromDt instanceof \DateTimeInterface ? $dateFromDt->format('d.m.Y') : '';
            $to = $dateToDt instanceof \DateTimeInterface ? $dateToDt->format('d.m.Y') : '';
            $searchResultContext = $from !== '' && $to !== ''
                ? 'für den Zeitraum ' . $from . ' bis ' . $to
                : ($from !== '' ? 'ab ' . $from : 'bis ' . $to);
        }
        // Gruppierung nach Jahr wenn "Alle Jahre" angezeigt werden (unabhängig von enableYearFilter)
        $eventItemsByYear = ($selectedYear === 0)
            ? $this->groupEventItemsByYear($eventItems)
            : [];
        $stations = $this->stationRepository->findPrimaryBrigadeStations();

        $statistics = [];
        if ($showStatistics && in_array($templateVariant, ['bootstrap', 'foundation'], true)) {
            $statistics = $this->eventRepository->getYearlyStatistics(
                $activeStationUid,
                $statisticsYears,
                $showLongestOperation,
                $showShortestOperation
            );
            // Wenn ein konkretes Jahr ausgewählt ist, nur dieses Jahr in der Statistik anzeigen
            if ($selectedYear > 0 && !empty($statistics)) {
                $statistics = array_intersect_key($statistics, [$selectedYear => null]);
            }
            if (!empty($statistics)) {
                $this->registerStatisticsAssets($statistics);
            }
        }

        // Jahresgruppen mit eingebetteten Statistiken für Inline-Rendering
        // Vermeidet dynamischen Array-Zugriff {statisticsByYear.{year}} in Fluid (unzuverlässig)
        $yearGroupsWithStats = [];
        foreach ($eventItemsByYear as $year => $yearItems) {
            $yearGroupsWithStats[$year] = [
                'events'     => $yearItems,
                'statistics' => isset($statistics[$year]) ? [(int)$year => $statistics[$year]] : [],
            ];
        }
        // Block-Statistik nur anzeigen wenn keine Jahresgruppen aktiv (dann erfolgt Inline-Rendering)
        $showBlockStatistics = $showStatistics && empty($eventItemsByYear);
        // Listen-Layout: "compact" (Monatsgruppen, Kategorie-Filter) oder "classic" (bisherige Zeilen).
        // Standard "compact", auch für bestehende Plugins ohne gespeicherten FlexForm-Wert.
        $listLayout = (string)($this->settings['listLayout'] ?? '') === 'classic' ? 'classic' : 'compact';
        $compactList = [];
        if ($listLayout === 'compact' && !$this->excludeDisabledDetail && in_array($templateVariant, ['bootstrap', 'foundation'], true)) {
            $compactList = GeneralUtility::makeInstance(CompactListBuilder::class)
                ->build($eventItems, $statistics, $eventItemsByYear !== []);
        }

        $statisticsTitle = 'Einsatzstatistik';
        if ($selectedYear > 0) {
            $statisticsTitle .= ' ' . $selectedYear;
        }

        $this->view->assignMultiple([
            'events' => $events,
            'eventItems' => $eventItems,
            'eventItemsByYear' => $eventItemsByYear,
            'stations' => $stations,
            'searchWord' => $submittedSearchWord,
            'searchDateRange' => $searchDateRange,
            'hasSearchQuery' => $hasSearchQuery,
            'searchResultCount' => count($eventItems),
            'searchResultContext' => $searchResultContext,
            'enableSearch' => $enableSearch,
            'maxCount' => $maxCount,
            'dateFrom' => $dateFromDt,
            'dateTo'   => $dateToDt,
            'dateFromStr'         => $dateFromStr,
            'dateToStr'           => $dateToStr,
            'enableDateFilter'    => $enableDateFilter,
            'templateVariant' => $templateVariant,
            'detailPageUid' => $detailPageUid,
            'defaultStationUid'   => $defaultStationUid,
            'activeStationUid'    => $activeStationUid,
            'settings'            => $viewSettings,
            'statistics'          => $statistics,
            'statisticsTitle'     => $statisticsTitle,
            'yearGroupsWithStats' => $yearGroupsWithStats,
            'showStatistics'      => $showStatistics,
            'showBlockStatistics' => $showBlockStatistics,
            'statisticsPosition'  => $statisticsPosition,
            'widgetTitle'         => $widgetTitle,
            'listPageUid'         => $listPageUid,
            'enableYearFilter'    => $enableYearFilter,
            'availableYears'      => $availableYears,
            'selectedYear'        => $selectedYear,
            'showStationFilter'   => $showStationFilter,
            'activeStationName'   => $activeStationName,
            'showMapView'         => $showMapView,
            'mapPosition'         => $mapPosition,
            'listLayout'          => $listLayout,
            'compactList'         => $compactList,
        ]);

        return $this->htmlResponse();
    }

    /**
     * Cards-Ansicht (wie list, aber mit Card-Grid Layout)
     */
    public function cardsAction(
        ?string $searchWord = null,
        ?string $station = null,
        ?string $year = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): ResponseInterface {
        $this->excludeDisabledDetail = true;
        return $this->listAction($searchWord, $station, $year, $dateFrom, $dateTo);
    }

    /**
     * RSS 2.0-Feed der neuesten Einsätze, optional gefiltert nach Ortsfeuerwehr
     */
    public function rssAction(): ResponseInterface
    {
        $stationUid    = (int)($this->settings['station'] ?? 0);
        $maxCount      = (int)($this->settings['maxCount'] ?? 20);
        $detailPageUid = $this->normalizeDetailPageUid($this->settings['detailPageUid'] ?? null);
        $feedTitle     = trim((string)($this->settings['feedTitle'] ?? ''));

        $events = $stationUid > 0
            ? $this->eventRepository->findFilteredByStation($stationUid, null, null, $maxCount)
            : $this->eventRepository->findFiltered(null, null, $maxCount);

        $stationName = '';
        if ($stationUid > 0) {
            $station = $this->stationRepository->findByUid($stationUid);
            if ($station) {
                $stationName = $station->getName();
            }
        }

        $this->view->assignMultiple([
            'events'        => $events,
            'stationName'   => $stationName,
            'feedTitle'     => $feedTitle,
            'detailPageUid' => $detailPageUid,
        ]);

        return $this->htmlResponse()
            ->withHeader('Content-Type', 'application/rss+xml; charset=utf-8');
    }

    /**
     * Jahresstatistik nach Kategorie, optional gefiltert nach Ortsfeuerwehr
     */
    public function statisticsAction(?string $station = null): ResponseInterface
    {
        $defaultStationUid = (int)($this->settings['station'] ?? 0);
        $selectedStationUid = $this->normalizeRecordUid($station);
        $stations = $this->stationRepository->findPrimaryBrigadeStations();
        $allowedStationUids = [];
        foreach ($stations as $stationRecord) {
            $allowedStationUids[] = (int)$stationRecord->getUid();
        }

        $stationUid = $selectedStationUid > 0 ? $selectedStationUid : $defaultStationUid;
        if ($stationUid > 0 && !in_array($stationUid, $allowedStationUids, true)) {
            $stationUid = 0;
        }
        if ($stationUid === 0) {
            $firstStation = $stations->getFirst();
            if ($firstStation) {
                $stationUid = (int)$firstStation->getUid();
            }
        }

        $statisticsYears  = (int)($this->settings['statisticsYears'] ?? 0);
        $showMonthlyChart = (bool)($this->settings['showMonthlyChart'] ?? true);
        $showStationFilter = (bool)($this->settings['showStationFilter'] ?? true);
        $showCategoryStatistics = (bool)($this->settings['showCategoryStatistics'] ?? true);
        $showLongestOperation = (bool)($this->settings['showLongestOperation'] ?? false);
        $showShortestOperation = (bool)($this->settings['showShortestOperation'] ?? false);
        $showStationYearOverview = (bool)($this->settings['showStationYearOverview'] ?? false);
        $needsYearlyStatistics = $showCategoryStatistics || $showLongestOperation || $showShortestOperation;
        $statistics = $needsYearlyStatistics
            ? $this->eventRepository->getYearlyStatistics(
                $stationUid,
                $statisticsYears,
                $showLongestOperation,
                $showShortestOperation
            )
            : [];
        $monthlyStatistics = $showMonthlyChart
            ? $this->eventRepository->getMonthlyStatistics($stationUid, $statisticsYears)
            : [];

        $stationName = '';
        if ($stationUid > 0) {
            $station = $this->stationRepository->findByUid($stationUid);
            if ($station) {
                $stationName = $station->getName();
            }
        }
        $statisticsTitleYear = count($statistics) === 1 ? (string)array_key_first($statistics) : '';

        if (!empty($statistics) || $showStationYearOverview || $showMonthlyChart) {
            $this->registerStatisticsAssets($statistics);
        }

        $stationYearOverview = [];
        if ($showStationYearOverview) {
            $stationYearOverview = $this->eventRepository->getStationYearOverview(
                $allowedStationUids,
                (int)date('Y')
            );
            $stationYearOverview['rows'] = [];
            foreach ($stations as $stationRecord) {
                $stationRecordUid = (int)$stationRecord->getUid();
                $stationData = $stationYearOverview['stations'][$stationRecordUid] ?? [
                    'count' => 0,
                    'duration' => '—',
                ];
                $stationYearOverview['rows'][] = [
                    'name' => $stationRecord->getName(),
                    'count' => $stationData['count'],
                    'duration' => $stationData['duration'],
                ];
            }
        }

        $this->view->assignMultiple([
            'statistics'        => $statistics,
            'monthlyStatistics' => $monthlyStatistics,
            'showMonthlyChart'  => $showMonthlyChart,
            'showCategoryStatistics' => $showCategoryStatistics,
            'showStationYearOverview' => $showStationYearOverview,
            'stationYearOverview' => $stationYearOverview,
            'stationName'       => $stationName,
            'statisticsTitleYear' => $statisticsTitleYear,
            'stationUid'        => $stationUid,
            'activeStationUid'  => $stationUid,
            'stations'          => $stations,
            'showStationFilter' => $showStationFilter,
        ]);

        return $this->htmlResponse();
    }

    /**
     * Detailansicht eines einzelnen Einsatzes
     */
    public function showAction(Event $event, ?string $station = null): ResponseInterface
    {
        $event = $this->eventRepository->findByUid($event->getUid());
        $pageTitle = trim((string)$event->getTitle());
        if ($event->getStart() instanceof \DateTimeInterface) {
            $pageTitle .= ' – ' . $event->getStart()->format('d.m.Y');
        }
        GeneralUtility::makeInstance(EventPageTitleProvider::class)
            ->setTitle($pageTitle);
        $groupedVehicleData = $this->groupVehiclesByBrigadeAndStation($event);
        $templateVariant = $this->normalizeTemplateVariant(
            (string)($this->settings['templateVariant'] ?? 'bootstrap'),
            true
        );

        $defaultStationUid = (int)($this->settings['defaultStation'] ?? 0);
        $selectedStationUid = $this->normalizeRecordUid($station);
        $activeStationUid = $selectedStationUid > 0 ? $selectedStationUid : $defaultStationUid;

        if ($activeStationUid === 0) {
            $firstStation = $this->stationRepository->findPrimaryBrigadeStations()->getFirst();
            if ($firstStation) {
                $activeStationUid = (int)$firstStation->getUid();
            }
        }

        $displayNumber = '';
        $displayPlainNumber = '';
        $displayStationName = '';

        if ($event instanceof Event && $event->getStart() instanceof \DateTime) {
            foreach ($event->getStations() as $stationObject) {
                $stationUid = (int)$stationObject->getUid();

                if ($stationUid <= 0) {
                    continue;
                }

                $runningNumber = $this->eventRepository->countByStationAndYearUntil(
                    $event->getStart(),
                    $stationUid,
                    (int)$event->getUid()
                );

                $plainNumber = str_pad((string)$runningNumber, 3, '0', STR_PAD_LEFT);

                $prefix = '';
                if (method_exists($stationObject, 'getPrefix')) {
                    $prefix = trim((string)$stationObject->getPrefix());
                }

                $formattedNumber = $prefix !== ''
                    ? $prefix . '/' . $plainNumber
                    : $plainNumber;

                if ($activeStationUid > 0 && $stationUid === $activeStationUid) {
                    $displayNumber = $formattedNumber;
                    $displayPlainNumber = $plainNumber;
                    $displayStationName = $stationObject->getName();
                    break;
                }

                if ($displayNumber === '') {
                    $displayNumber = $formattedNumber;
                    $displayPlainNumber = $plainNumber;
                    $displayStationName = $stationObject->getName();
                }
            }
        }

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addCssFile('EXT:rescue_reports/Resources/Public/Vendor/glightbox/glightbox.min.css');
        $pageRenderer->addJsFooterLibrary(
            'glightbox',
            'EXT:rescue_reports/Resources/Public/Vendor/glightbox/glightbox.min.js',
            'text/javascript',
            false,
            false,
            '',
            true
        );
        $this->registerOpenGraphMetaTags($event);
        $this->registerSearchEngineMetaTags($event);

        $this->view->assignMultiple([
            'event' => $event,
            'groupedVehicleData' => $groupedVehicleData,
            'detailPageUid' => $this->normalizeDetailPageUid($this->settings['detailPageUid'] ?? null),
            'defaultStationUid' => $defaultStationUid,
            'activeStationUid' => $activeStationUid,
            'displayNumber' => $displayNumber,
            'displayPlainNumber' => $displayPlainNumber,
            'displayStationName' => $displayStationName,
            'templateVariant' => $templateVariant,
            'settings' => $this->settings,
        ]);

        return $this->htmlResponse();
    }

    /**
     * Baut View-Daten für dynamische Einsatznummern pro Station auf.
     */
    protected function buildEventItemsForStations(iterable $events, int $selectedStationUid = 0): array
    {
        $items = [];

        foreach ($events as $event) {
            if (!$event instanceof Event) {
                continue;
            }

            $start = $event->getStart();
            $stationNumbers = [];
            $primaryNumber = '';
            $primaryPlainNumber = '';
            $primaryStationName = '';

            if ($start instanceof \DateTime) {
                foreach ($event->getStations() as $station) {
                    $stationUid = (int)$station->getUid();

                    if ($stationUid <= 0) {
                        continue;
                    }

                    $runningNumber = $this->eventRepository->countByStationAndYearUntil(
                        $start,
                        $stationUid,
                        (int)$event->getUid()
                    );

                    $plainNumber = str_pad((string)$runningNumber, 3, '0', STR_PAD_LEFT);

                    $prefix = '';
                    if (method_exists($station, 'getPrefix')) {
                        $prefix = trim((string)$station->getPrefix());
                    }

                    $formattedNumber = $prefix !== ''
                        ? $prefix . '/' . $plainNumber
                        : $plainNumber;

                    $stationNumbers[] = [
                        'station' => $station,
                        'stationUid' => $stationUid,
                        'stationName' => $station->getName(),
                        'prefix' => $prefix,
                        'runningNumber' => $runningNumber,
                        'formattedNumber' => $formattedNumber,
                        'plainNumber' => $plainNumber,
                        'year' => $start->format('Y'),
                    ];

                    if ($selectedStationUid > 0 && $stationUid === $selectedStationUid) {
                        $primaryNumber = $formattedNumber;
                        $primaryPlainNumber = $plainNumber;
                        $primaryStationName = $station->getName();
                    }

                    if ($primaryNumber === '') {
                        $primaryNumber = $formattedNumber;
                        $primaryPlainNumber = $plainNumber;
                        $primaryStationName = $station->getName();
                    }
                }
            }

            $items[] = [
                'event' => $event,
                'number' => $primaryNumber,
                'plainNumber' => $primaryPlainNumber,
                'stationName' => $primaryStationName,
                'numbers' => $stationNumbers,
            ];
        }

        return $items;
    }

    /**
     * Gruppiert Event-Items nach Einsatzjahr (absteigend), für die Ansicht "Alle Jahre".
     *
     * @param array<int,array<string,mixed>> $eventItems
     * @return array<string,array<int,array<string,mixed>>>
     */
    protected function groupEventItemsByYear(array $eventItems): array
    {
        $grouped = [];

        foreach ($eventItems as $item) {
            $event = $item['event'] ?? null;
            if (!$event instanceof Event) {
                continue;
            }

            $start = $event->getStart();
            $year = $start instanceof \DateTimeInterface ? $start->format('Y') : 'Unbekannt';
            $grouped[$year][] = $item;
        }

        if (isset($grouped['Unbekannt'])) {
            $unknown = $grouped['Unbekannt'];
            unset($grouped['Unbekannt']);
            $grouped['Unbekannt'] = $unknown;
        }

        return $grouped;
    }

    /**
     * Gruppiert Fahrzeuge nach Feuerwehr und Standort
     */
    protected function groupVehiclesByBrigadeAndStation(Event $event): array
    {
        $grouped = [];
        $eventVehicles = $event->getVehicles()->toArray();

        foreach ($event->getStations() as $station) {
            $brigade = $station->getBrigade();

            $brigadeUid = $brigade ? (int)$brigade->getUid() : 0;
            $brigadeName = $brigade ? $brigade->getName() : 'Unbekannt';
            $brigadeSorting = ($brigade && method_exists($brigade, 'getSorting')) ? (int)$brigade->getSorting() : 9999;

            $stationName = $station->getName();
            $stationSorting = method_exists($station, 'getSorting') ? (int)$station->getSorting() : 9999;

            // Fahrzeuge der Station in DB-Sortierung laden
            $vehicles = [];
            $stationVehicles = $this->getSortedVehiclesForStation($station);

            foreach ($stationVehicles as $vehicle) {
                if (in_array($vehicle, $eventVehicles, true)) {
                    $vehicles[] = $vehicle;
                }
            }

            if (!isset($grouped[$brigadeUid])) {
                $grouped[$brigadeUid] = [
                    'uid' => $brigadeUid,
                    'name' => $brigadeName,
                    'sorting' => $brigadeSorting,
                    'stations' => [],
                ];
            }

            $grouped[$brigadeUid]['stations'][] = [
                'name' => $stationName,
                'sorting' => $stationSorting,
                'vehicles' => $vehicles,
            ];
        }

        // 🔽 Brigaden sortieren
        $grouped = array_values($grouped);

        usort(
            $grouped,
            static function (array $a, array $b): int {
                $compare = $a['sorting'] <=> $b['sorting'];
                if ($compare !== 0) {
                    return $compare;
                }

                return strcmp((string)$a['name'], (string)$b['name']);
            }
        );

        // 🔽 Stationen sortieren
        foreach ($grouped as &$group) {
            if (isset($group['stations']) && is_array($group['stations'])) {
                usort(
                    $group['stations'],
                    static function (array $a, array $b): int {
                        $compare = $a['sorting'] <=> $b['sorting'];
                        if ($compare !== 0) {
                            return $compare;
                        }

                        return strcmp((string)$a['name'], (string)$b['name']);
                    }
                );
            }
        }
        unset($group);

        return $grouped;
    }

    /**
     * Liefert die Fahrzeuge einer Station in DB-Sortierung.
     */
    protected function getSortedVehiclesForStation($station): array
    {
        $stationUid = (int)$station->getUid();
        if ($stationUid <= 0) {
            return [];
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_vehicle');

        $queryBuilder->getRestrictions()->removeAll();

        $rows = $queryBuilder
            ->select('uid')
            ->from('tx_rescuereports_domain_model_vehicle')
            ->where(
                $queryBuilder->expr()->eq(
                    'station',
                    $queryBuilder->createNamedParameter($stationUid, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'deleted',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'hidden',
                    $queryBuilder->createNamedParameter(0, PDO::PARAM_INT)
                )
            )
            ->orderBy('sorting', 'ASC')
            ->addOrderBy('uid', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn();

        if (empty($rows)) {
            return [];
        }

        $sortedVehicles = [];
        $stationVehicles = $station->getVehicles()->toArray();

        foreach ($rows as $vehicleUid) {
            $vehicleUid = (int)$vehicleUid;

            foreach ($stationVehicles as $vehicle) {
                if ((int)$vehicle->getUid() === $vehicleUid) {
                    $sortedVehicles[] = $vehicle;
                    break;
                }
            }
        }

        return $sortedVehicles;
    }

    protected function normalizeTemplateVariant(string $variant, bool $detailView = false): string
    {
        $legacyVariants = [
            'newdesign' => 'bootstrap',
            'standard' => 'bootstrap',
            'sidebar' => 'sidebar-foundation',
            'newdesignsidebar' => 'sidebar-bootstrap',
        ];
        $variant = $legacyVariants[$variant] ?? $variant;

        if (!in_array($variant, ['bootstrap', 'foundation', 'sidebar-bootstrap', 'sidebar-foundation'], true)) {
            return 'bootstrap';
        }

        if ($detailView && in_array($variant, ['sidebar-bootstrap', 'sidebar-foundation'], true)) {
            return 'bootstrap';
        }

        return $variant;
    }

    /**
     * Adds cacheable statistics assets and category-specific CSS fallbacks.
     *
     * @param array<int, array<string, mixed>> $statistics
     */
    protected function registerStatisticsAssets(array $statistics): void
    {
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addCssFile('EXT:rescue_reports/Resources/Public/Css/statistics.css');
        $pageRenderer->addJsFooterFile('EXT:rescue_reports/Resources/Public/Js/statistics-pie-tooltip.js');

        $seenTooltipUids = [];
        foreach ($statistics as $yearData) {
            foreach ($yearData['categories'] as $category) {
                $uid = (int)$category['uid'];
                if ($uid <= 0 || in_array($uid, $seenTooltipUids, true)) {
                    continue;
                }
                $seenTooltipUids[] = $uid;
                $pageRenderer->addCssInlineBlock(
                    'rescueStatisticsPieTooltipFallback' . $uid,
                    "html:not(.rescue-pie-tooltip--enhanced) .pie-wrap:has(.pie-slice--{$uid}:hover) .pie-tooltip--{$uid}{display:block;}"
                );
            }
        }
    }

    /**
     * Registers social-media preview data for an incident detail page.
     */
    protected function registerOpenGraphMetaTags(Event $event): void
    {
        $title = trim((string)$event->getTitle());
        $description = trim(preg_replace('/\s+/', ' ', strip_tags((string)$event->getDescription())) ?? '');
        $metaTagManager = GeneralUtility::makeInstance(MetaTagManagerRegistry::class);

        $metaTagManager->getManagerForProperty('og:title')->addProperty('og:title', $title);
        $metaTagManager->getManagerForProperty('og:type')->addProperty('og:type', 'article');
        $metaTagManager->getManagerForProperty('twitter:card')->addProperty('twitter:card', 'summary_large_image');
        $metaTagManager->getManagerForProperty('twitter:title')->addProperty('twitter:title', $title);

        if ($description !== '') {
            $metaTagManager->getManagerForProperty('og:description')->addProperty('og:description', $description);
            $metaTagManager->getManagerForProperty('twitter:description')->addProperty('twitter:description', $description);
        }

        $imageUri = $this->getFirstEventImageUri($event);
        if ($imageUri !== '') {
            $metaTagManager->getManagerForProperty('og:image')->addProperty('og:image', $imageUri);
            $metaTagManager->getManagerForProperty('twitter:image')->addProperty('twitter:image', $imageUri);
        }
    }

    /**
     * Adds a canonical URL and Schema.org data for an incident detail page.
     */
    protected function registerSearchEngineMetaTags(Event $event): void
    {
        $canonicalUrl = $this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->uriFor('show', ['event' => $event], 'Event');

        $title = trim((string)$event->getTitle());
        $description = trim(preg_replace('/\s+/', ' ', strip_tags((string)$event->getDescription())) ?? '');
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $title,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonicalUrl,
            ],
        ];

        if ($description !== '') {
            $schema['description'] = $description;
        }
        if ($event->getStart() instanceof \DateTimeInterface) {
            $schema['datePublished'] = $event->getStart()->format(DATE_ATOM);
        }

        $imageUri = $this->getFirstEventImageUri($event);
        if ($imageUri !== '') {
            $schema['image'] = $imageUri;
        }

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addHeaderData(
            '<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        );
        $pageRenderer->addHeaderData(
            '<script type="application/ld+json">' . json_encode($schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . '</script>'
        );
    }

    protected function getFirstEventImageUri(Event $event): string
    {
        foreach ($event->getImages() as $image) {
            try {
                $imageUri = GeneralUtility::makeInstance(ImageService::class)->getImageUri($image, true);
            } catch (\Throwable $exception) {
                continue;
            }

            if ($imageUri !== '') {
                return $imageUri;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    protected function normalizeCardImageSettings(array $settings): array
    {
        $mode = (string)($settings['cardImageMode'] ?? 'format');
        $settings['cardImageMode'] = in_array($mode, ['format', 'servercrop'], true)
            ? $mode
            : 'format';

        $ratio = (string)($settings['cardImageRatio'] ?? '16 / 9');
        $settings['cardImageRatio'] = in_array($ratio, ['16 / 9', '4 / 3', '1 / 1'], true)
            ? $ratio
            : '16 / 9';

        $settings['cardImageWidth'] = max(1, min(4000, (int)($settings['cardImageWidth'] ?? 800)));
        $settings['cardImageHeight'] = max(1, min(4000, (int)($settings['cardImageHeight'] ?? 500)));

        return $settings;
    }

    /**
     * Wandelt FlexForm-Datumswerte zuverlässig in DateTime um
     */
    /**
     * Recognizes a date entered as the complete search term.
     *
     * @return array{from: string, to: string}|null
     */
    protected function extractDateRangeFromSearchTerm(string $searchTerm): ?array
    {
        $searchTerm = trim($searchTerm);
        $date = null;

        if (preg_match('/^\d{4}$/', $searchTerm)) {
            return ['from' => $searchTerm . '-01-01', 'to' => $searchTerm . '-12-31'];
        }

        foreach (['!Y-m-d', '!d.m.Y', '!d/m/Y'] as $format) {
            $candidate = \DateTime::createFromFormat($format, $searchTerm);
            $errors = \DateTime::getLastErrors();
            if ($candidate instanceof \DateTime && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $date = $candidate;
                break;
            }
        }

        if (!$date instanceof \DateTime) {
            return null;
        }

        $formattedDate = $date->format('Y-m-d');
        return ['from' => $formattedDate, 'to' => $formattedDate];
    }

    protected function createDateTimeFromFlexFormValue($value): ?\DateTime
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
     * Normalisiert das Seitenfeld aus der FlexForm
     */
    protected function normalizeDetailPageUid($value): ?int
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (is_string($value) && strpos($value, ',') !== false) {
            $parts = explode(',', $value);
            $value = $parts[0] ?? null;
        }

        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        return (int)$value;
    }

    /**
     * Normalisiert eine Datensatz-UID aus Request/FlexForm
     */
    protected function normalizeRecordUid($value): int
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (is_string($value) && strpos($value, ',') !== false) {
            $parts = explode(',', $value);
            $value = $parts[0] ?? null;
        }

        if (is_string($value) && strpos($value, '_') !== false) {
            $parts = explode('_', $value);
            $value = end($parts);
        }

        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return 0;
        }

        return (int)$value;
    }
}
