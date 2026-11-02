<?php
namespace Nkfire\RescueReports\Controller;

use Nkfire\RescueReports\Domain\Model\Event;
use Nkfire\RescueReports\Domain\Repository\EventRepository;
use Nkfire\RescueReports\Domain\Repository\StationRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use PDO;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EventController extends ActionController
{
    protected EventRepository $eventRepository;
    protected StationRepository $stationRepository;

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
        $templateVariant     = (string)($this->settings['templateVariant'] ?? 'bootstrap');
        $showStatistics      = (bool)($this->settings['showStatistics'] ?? false);
        $statisticsPosition  = (string)($this->settings['statisticsPosition'] ?? 'below');
        $statisticsYears     = (int)($this->settings['statisticsYears'] ?? 0);
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

        // Backward-Compat: alte Werte aus bestehenden DB-Einträgen auf neue Namen mappen
        $templateVariantCompat = [
            'newdesign'      => 'bootstrap',
            'standard'       => 'bootstrap',
            'sidebar'        => 'sidebar-foundation',
            'newdesignsidebar' => 'sidebar-bootstrap',
        ];
        if (isset($templateVariantCompat[$templateVariant])) {
            $templateVariant = $templateVariantCompat[$templateVariant];
        }

        $allowedTemplateVariants = [
            'bootstrap',
            'foundation',
            'sidebar-bootstrap',
            'sidebar-foundation',
        ];

        if (!in_array($templateVariant, $allowedTemplateVariants, true)) {
            $templateVariant = 'bootstrap';
        }

        $searchWord = trim((string)($searchWord ?? ''));

        $dateFrom = $dateFromValue;
        $dateTo = $dateToValue;

        // Jahresauswahl überschreibt FlexForm-Datumsbereich (unabhängig von enableYearFilter)
        if ($selectedYear > 0) {
            $dateFrom = $selectedYear . '-01-01';
            $dateTo   = $selectedYear . '-12-31';
        } elseif ($yearFilterDefault === 'all' || $enableYearFilter) {
            // "Alle Jahre" als Standard oder Jahresfilter aktiv → FlexForm-Datumseinschränkungen aufheben
            $dateFrom = null;
            $dateTo   = null;
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
                    $maxCount
                );
            }
        } else {
            if ($enableSearch && $searchWord !== '') {
                $events = $this->eventRepository->search($searchWord, $dateFrom, $dateTo, $maxCount);
            } else {
                $events = $this->eventRepository->findFiltered($dateFrom, $dateTo, $maxCount);
            }
        }

        $eventItems = $this->buildEventItemsForStations($events, $activeStationUid);
        // Gruppierung nach Jahr wenn "Alle Jahre" angezeigt werden (unabhängig von enableYearFilter)
        $eventItemsByYear = ($selectedYear === 0)
            ? $this->groupEventItemsByYear($eventItems)
            : [];
        $stations = $this->stationRepository->findPrimaryBrigadeStations();

        $statistics = [];
        if ($showStatistics && in_array($templateVariant, ['bootstrap', 'foundation'], true)) {
            $statistics = $this->eventRepository->getYearlyStatistics($activeStationUid, $statisticsYears);
            // Wenn ein konkretes Jahr ausgewählt ist, nur dieses Jahr in der Statistik anzeigen
            if ($selectedYear > 0 && !empty($statistics)) {
                $statistics = array_intersect_key($statistics, [$selectedYear => null]);
            }
            if (!empty($statistics)) {
                $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
                $pageRenderer->addCssInlineBlock(
                    'rescueStatisticsLayout',
                    '.rescue-statistics__layout{display:flex;gap:2rem;align-items:flex-start;flex-wrap:wrap;margin:1rem 0 2rem;}'
                    . '.rescue-statistics__chart-wrap{flex:0 0 220px;}'
                    . '.rescue-statistics__table-wrap{flex:1 1 300px;}'
                    . '.rescue-statistics__table{width:100%;border-collapse:collapse;}'
                    . '.rescue-statistics__table th,.rescue-statistics__table td{padding:.35rem .6rem;border-bottom:1px solid #ddd;vertical-align:middle;}'
                    . '.rescue-statistics__num{text-align:right;white-space:nowrap;}'
                    . '.rescue-statistics__dot{display:inline-block;width:14px;height:14px;border-radius:50%;}'
                    . '.rescue-statistics__total{font-size:.85em;font-weight:normal;color:#666;margin-left:.5rem;}'
                    . '.rescue-statistics__year-title{margin-bottom:.25rem;}'
                    . '.rescue-statistics__compare{font-size:.85em;color:#666;margin-top:.5rem;}'
                );
                $pageRenderer->addCssInlineBlock(
                    'rescueStatisticsPie',
                    '.rescue-statistics svg path,.rescue-statistics svg circle{'
                    . 'transition:transform .15s ease-out;cursor:pointer;transform-origin:110px 110px;}'
                    . '.rescue-statistics svg path:hover,.rescue-statistics svg circle:hover{'
                    . 'transform:scale(1.08);}'
                );
                $pageRenderer->addCssInlineBlock(
                    'rescueStatisticsPieTooltip',
                    '.pie-wrap{position:relative;display:inline-block;}'
                    . '.pie-tooltip{display:none;position:absolute;top:calc(100% + 8px);left:50%;transform:translateX(-50%);'
                    .   'min-width:0;width:max-content;max-width:min(360px,calc(100vw - 20px));'
                    .   'background:rgba(255,255,255,.97);border:1px solid #ddd;border-radius:4px;'
                    .   'padding:6px 10px;z-index:20;box-shadow:0 2px 6px rgba(0,0,0,.15);'
                    .   'pointer-events:none;font-size:.82em;line-height:1.4;}'
                    . 'html.rescue-pie-tooltip--enhanced .pie-tooltip{position:fixed;left:0;top:0;transform:none;}'
                    . '.pie-tooltip strong{display:block;margin-bottom:3px;}'
                    . '.pie-tooltip__types{margin:2px 0 4px;padding-left:14px;}'
                    . '.pie-tooltip__meta{color:#666;font-size:.9em;}'
                );
                $seenTooltipUids = [];
                foreach ($statistics as $yearData) {
                    foreach ($yearData['categories'] as $cat) {
                        $uid = (int)$cat['uid'];
                        if ($uid > 0 && !in_array($uid, $seenTooltipUids, true)) {
                            $seenTooltipUids[] = $uid;
                            $pageRenderer->addCssInlineBlock(
                                'rescueStatisticsPieTooltipFallback' . $uid,
                                "html:not(.rescue-pie-tooltip--enhanced) .pie-wrap:has(.pie-slice--{$uid}:hover) .pie-tooltip--{$uid}{display:block;}"
                            );
                        }
                    }
                }
                $pageRenderer->addJsInlineCode(
                    'rescueStatisticsPieTooltip',
                    '(function(){'
                    . 'if(window.__rescuePieTooltipInit){return;}window.__rescuePieTooltipInit=true;'
                    . 'document.documentElement.classList.add("rescue-pie-tooltip--enhanced");'
                    . 'var clamp=function(v,min,max){return Math.max(min,Math.min(max,v));};'
                    . 'var position=function(t,e){'
                    . 'var gap=14;var rect=t.getBoundingClientRect();'
                    . 'var x=e.clientX+gap;var y=e.clientY+gap;'
                    . 'if(x+rect.width>window.innerWidth-8){x=e.clientX-rect.width-gap;}'
                    . 'if(y+rect.height>window.innerHeight-8){y=e.clientY-rect.height-gap;}'
                    . 't.style.left=clamp(x,8,Math.max(8,window.innerWidth-rect.width-8))+"px";'
                    . 't.style.top=clamp(y,8,Math.max(8,window.innerHeight-rect.height-8))+"px";'
                    . '};'
                    . 'document.addEventListener("mouseover",function(e){'
                    . 'var slice=e.target.closest(".pie-slice[data-category-uid]");if(!slice){return;}'
                    . 'var wrap=slice.closest(".pie-wrap");if(!wrap){return;}'
                    . 'var uid=slice.getAttribute("data-category-uid");'
                    . 'var tooltip=wrap.querySelector(".pie-tooltip[data-category-uid=\'"+uid+"\']");'
                    . 'if(!tooltip){return;}tooltip.style.display="block";position(tooltip,e);'
                    . '});'
                    . 'document.addEventListener("mousemove",function(e){'
                    . 'var slice=e.target.closest(".pie-slice[data-category-uid]");if(!slice){return;}'
                    . 'var wrap=slice.closest(".pie-wrap");if(!wrap){return;}'
                    . 'var uid=slice.getAttribute("data-category-uid");'
                    . 'var tooltip=wrap.querySelector(".pie-tooltip[data-category-uid=\'"+uid+"\']");'
                    . 'if(!tooltip||tooltip.style.display!=="block"){return;}position(tooltip,e);'
                    . '});'
                    . 'document.addEventListener("mouseout",function(e){'
                    . 'var slice=e.target.closest(".pie-slice[data-category-uid]");if(!slice){return;}'
                    . 'var wrap=slice.closest(".pie-wrap");if(!wrap){return;}'
                    . 'var uid=slice.getAttribute("data-category-uid");'
                    . 'var tooltip=wrap.querySelector(".pie-tooltip[data-category-uid=\'"+uid+"\']");'
                    . 'if(tooltip){tooltip.style.display="none";}'
                    . '});'
                    . '})();'
                );
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

        $this->view->assignMultiple([
            'events' => $events,
            'eventItems' => $eventItems,
            'eventItemsByYear' => $eventItemsByYear,
            'stations' => $stations,
            'searchWord' => $searchWord,
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
            'settings'            => $this->settings,
            'statistics'          => $statistics,
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
        ]);

        return $this->htmlResponse();
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
        $statistics       = $this->eventRepository->getYearlyStatistics($stationUid, $statisticsYears);
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

        if (!empty($statistics)) {
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->addCssInlineBlock(
                'rescueStatisticsLayout',
                '.rescue-statistics__station-filter{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin:.25rem 0 1rem;}'
                . '.rescue-statistics__station-filter .form-select{min-width:220px;}'
                . '.rescue-statistics__layout{display:flex;gap:2rem;align-items:flex-start;flex-wrap:wrap;margin:1rem 0 2rem;}'
                . '.rescue-statistics__chart-wrap{flex:0 0 220px;}'
                . '.rescue-statistics__table-wrap{flex:1 1 300px;}'
                . '.rescue-statistics__table{width:100%;border-collapse:collapse;}'
                . '.rescue-statistics__table th,.rescue-statistics__table td{padding:.35rem .6rem;border-bottom:1px solid #ddd;vertical-align:middle;}'
                . '.rescue-statistics__num{text-align:right;white-space:nowrap;}'
                . '.rescue-statistics__dot{display:inline-block;width:14px;height:14px;border-radius:50%;}'
                . '.rescue-statistics__total{font-size:.85em;font-weight:normal;color:#666;margin-left:.5rem;}'
                . '.rescue-statistics__year-title{margin-bottom:.25rem;}'
                . '.rescue-statistics__compare{font-size:.85em;color:#666;margin-top:.5rem;}'
            );
            $pageRenderer->addCssInlineBlock(
                'rescueStatisticsPie',
                '.rescue-statistics svg path,.rescue-statistics svg circle{'
                . 'transition:transform .15s ease-out;cursor:pointer;transform-origin:110px 110px;}'
                . '.rescue-statistics svg path:hover,.rescue-statistics svg circle:hover{'
                . 'transform:scale(1.08);}'
            );
            $pageRenderer->addCssInlineBlock(
                'rescueStatisticsPieTooltip',
                '.pie-wrap{position:relative;display:inline-block;}'
                . '.pie-tooltip{display:none;position:absolute;top:calc(100% + 8px);left:50%;transform:translateX(-50%);'
                .   'min-width:0;width:max-content;max-width:min(360px,calc(100vw - 20px));'
                .   'background:rgba(255,255,255,.97);border:1px solid #ddd;border-radius:4px;'
                .   'padding:6px 10px;z-index:20;box-shadow:0 2px 6px rgba(0,0,0,.15);'
                .   'pointer-events:none;font-size:.82em;line-height:1.4;}'
                . 'html.rescue-pie-tooltip--enhanced .pie-tooltip{position:fixed;left:0;top:0;transform:none;}'
                . '.pie-tooltip strong{display:block;margin-bottom:3px;}'
                . '.pie-tooltip__types{margin:2px 0 4px;padding-left:14px;}'
                . '.pie-tooltip__meta{color:#666;font-size:.9em;}'
            );
            $seenTooltipUids = [];
            foreach ($statistics as $yearData) {
                foreach ($yearData['categories'] as $cat) {
                    $uid = (int)$cat['uid'];
                    if ($uid > 0 && !in_array($uid, $seenTooltipUids, true)) {
                        $seenTooltipUids[] = $uid;
                        $pageRenderer->addCssInlineBlock(
                            'rescueStatisticsPieTooltipFallback' . $uid,
                            "html:not(.rescue-pie-tooltip--enhanced) .pie-wrap:has(.pie-slice--{$uid}:hover) .pie-tooltip--{$uid}{display:block;}"
                        );
                    }
                }
            }
            $pageRenderer->addJsInlineCode(
                'rescueStatisticsPieTooltip',
                '(function(){'
                . 'if(window.__rescuePieTooltipInit){return;}window.__rescuePieTooltipInit=true;'
                . 'document.documentElement.classList.add("rescue-pie-tooltip--enhanced");'
                . 'var clamp=function(v,min,max){return Math.max(min,Math.min(max,v));};'
                . 'var position=function(t,e){'
                . 'var gap=14;var rect=t.getBoundingClientRect();'
                . 'var x=e.clientX+gap;var y=e.clientY+gap;'
                . 'if(x+rect.width>window.innerWidth-8){x=e.clientX-rect.width-gap;}'
                . 'if(y+rect.height>window.innerHeight-8){y=e.clientY-rect.height-gap;}'
                . 't.style.left=clamp(x,8,Math.max(8,window.innerWidth-rect.width-8))+"px";'
                . 't.style.top=clamp(y,8,Math.max(8,window.innerHeight-rect.height-8))+"px";'
                . '};'
                . 'document.addEventListener("mouseover",function(e){'
                . 'var slice=e.target.closest(".pie-slice[data-category-uid]");if(!slice){return;}'
                . 'var wrap=slice.closest(".pie-wrap");if(!wrap){return;}'
                . 'var uid=slice.getAttribute("data-category-uid");'
                . 'var tooltip=wrap.querySelector(".pie-tooltip[data-category-uid=\'"+uid+"\']");'
                . 'if(!tooltip){return;}tooltip.style.display="block";position(tooltip,e);'
                . '});'
                . 'document.addEventListener("mousemove",function(e){'
                . 'var slice=e.target.closest(".pie-slice[data-category-uid]");if(!slice){return;}'
                . 'var wrap=slice.closest(".pie-wrap");if(!wrap){return;}'
                . 'var uid=slice.getAttribute("data-category-uid");'
                . 'var tooltip=wrap.querySelector(".pie-tooltip[data-category-uid=\'"+uid+"\']");'
                . 'if(!tooltip||tooltip.style.display!=="block"){return;}position(tooltip,e);'
                . '});'
                . 'document.addEventListener("mouseout",function(e){'
                . 'var slice=e.target.closest(".pie-slice[data-category-uid]");if(!slice){return;}'
                . 'var wrap=slice.closest(".pie-wrap");if(!wrap){return;}'
                . 'var uid=slice.getAttribute("data-category-uid");'
                . 'var tooltip=wrap.querySelector(".pie-tooltip[data-category-uid=\'"+uid+"\']");'
                . 'if(tooltip){tooltip.style.display="none";}'
                . '});'
                . '})();'
            );
            $pageRenderer->addCssInlineBlock(
                'rescueStatisticsBar',
                '.rescue-statistics__bar-chart{margin:2rem 0 1rem;}'
                . '.rescue-statistics__bar-chart-desktop{display:block;}'
                . '.rescue-statistics__bar-chart-mobile{display:none;}'
                . '.rescue-statistics__bar-chart svg rect.bar{transition:opacity .15s;cursor:default;}'
                . '.rescue-statistics__bar-chart svg rect.bar:hover{opacity:.8;}'
                . '.rescue-statistics__mobile-row{margin:0 0 .75rem;padding:.5rem .6rem;border:1px solid #e5e5e5;border-radius:6px;}'
                . '.rescue-statistics__mobile-month{font-weight:600;margin-bottom:.35rem;}'
                . '.rescue-statistics__mobile-line{display:flex;align-items:center;gap:.45rem;margin:.2rem 0;}'
                . '.rescue-statistics__mobile-year{flex:0 0 2.8rem;font-size:.9em;color:#555;}'
                . '.rescue-statistics__mobile-track{flex:1;height:10px;background:#f1f1f1;border-radius:999px;overflow:hidden;}'
                . '.rescue-statistics__mobile-fill{display:block;height:100%;min-width:2px;border-radius:999px;}'
                . '.rescue-statistics__mobile-count{flex:0 0 1.8rem;text-align:right;font-variant-numeric:tabular-nums;}'
                . '@media (max-width:720px){'
                . '.rescue-statistics__bar-chart-desktop{display:none;}'
                . '.rescue-statistics__bar-chart-mobile{display:block;}'
                . '}'
            );
        }

        $this->view->assignMultiple([
            'statistics'        => $statistics,
            'monthlyStatistics' => $monthlyStatistics,
            'showMonthlyChart'  => $showMonthlyChart,
            'stationName'       => $stationName,
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
        $groupedVehicleData = $this->groupVehiclesByBrigadeAndStation($event);
        $templateVariant = (string)($this->settings['templateVariant'] ?? 'bootstrap');
        $templateVariantCompat = [
            'newdesign'        => 'bootstrap',
            'standard'         => 'bootstrap',
            'sidebar'          => 'sidebar-foundation',
            'newdesignsidebar' => 'sidebar-bootstrap',
        ];
        if (isset($templateVariantCompat[$templateVariant])) {
            $templateVariant = $templateVariantCompat[$templateVariant];
        }
        // Sidebar-Varianten haben keine eigene Detailansicht → auf Bootstrap zurückfallen
        if (in_array($templateVariant, ['sidebar-bootstrap', 'sidebar-foundation'], true)) {
            $templateVariant = 'bootstrap';
        }
        if (!in_array($templateVariant, ['bootstrap', 'foundation'], true)) {
            $templateVariant = 'bootstrap';
        }

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
        $pageRenderer->addHeaderData('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3/dist/css/glightbox.min.css">');
        $pageRenderer->addJsFooterLibrary(
            'glightbox',
            'https://cdn.jsdelivr.net/npm/glightbox@3/dist/js/glightbox.min.js',
            'text/javascript',
            false,
            false,
            '',
            true
        );

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

    /**
     * Wandelt FlexForm-Datumswerte zuverlässig in DateTime um
     */
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
