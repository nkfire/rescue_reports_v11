<?php
declare(strict_types=1);

namespace Nkfire\RescueReports\Utility;

use Nkfire\RescueReports\Domain\Model\Event;

/**
 * Baut die Daten für den "Einsatz-Puls" (Jahres-Heatmap, Kennzahlen,
 * Tageszeit-Verteilung) aus den bereits aufbereiteten Event-Items.
 *
 * Bewusst ohne Datenbankzugriff: arbeitet nur auf den Items, die die
 * Liste ohnehin anzeigt, damit Puls und Liste immer übereinstimmen.
 */
class EventPulseBuilder
{
    private const FALLBACK_COLOR = '#c8102e';

    private const MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez',
    ];

    private const MONTHS_LONG = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
        7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];

    /**
     * @param array<int,array<string,mixed>> $eventItems
     * @param int $year 0 = Jahr des jüngsten Einsatzes verwenden
     * @return array<string,mixed> leeres Array, wenn nichts darzustellen ist
     */
    public function build(array $eventItems, int $year = 0, ?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable('now');

        $events = [];
        foreach ($eventItems as $item) {
            $event = $item['event'] ?? null;
            if ($event instanceof Event && $event->getStart() instanceof \DateTimeInterface) {
                $events[] = $event;
            }
        }
        if ($events === []) {
            return [];
        }

        if ($year <= 0) {
            foreach ($events as $event) {
                $year = max($year, (int)$event->getStart()->format('Y'));
            }
        }

        $events = array_values(array_filter(
            $events,
            static fn(Event $e): bool => (int)$e->getStart()->format('Y') === $year
        ));
        if ($events === []) {
            return [];
        }
        usort($events, static fn(Event $a, Event $b): int => $a->getStart() <=> $b->getStart());

        $byDay = [];
        $hours = array_fill(0, 24, 0);
        $months = array_fill(1, 12, 0);
        $legend = [];
        $totalMinutes = 0;
        $night = 0;
        $weekend = 0;

        foreach ($events as $event) {
            $start = $event->getStart();
            $category = $this->categoryOf($event);
            $byDay[$start->format('Y-m-d')][] = ['event' => $event, 'category' => $category];

            $hour = (int)$start->format('G');
            $hours[$hour]++;
            $months[(int)$start->format('n')]++;
            if ($hour >= 22 || $hour < 6) {
                $night++;
            }
            if ((int)$start->format('N') >= 6) {
                $weekend++;
            }

            $end = $event->getEnd();
            if ($end instanceof \DateTimeInterface) {
                $minutes = (int)round(($end->getTimestamp() - $start->getTimestamp()) / 60);
                if ($minutes < 0) {
                    // Ende ohne Datumswechsel erfasst (z. B. 23:34 – 00:18)
                    $minutes += 24 * 60;
                }
                if ($minutes > 0 && $minutes < 7 * 24 * 60) {
                    $totalMinutes += $minutes;
                }
            }

            $key = $category['title'];
            if (!isset($legend[$key])) {
                $legend[$key] = ['title' => $category['title'], 'color' => $category['color'], 'count' => 0];
            }
            $legend[$key]['count']++;
        }

        usort($legend, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        $count = count($events);
        $last = end($events);
        $isCurrentYear = (int)$now->format('Y') === $year;
        $yearStart = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $yearEnd = new \DateTimeImmutable(sprintf('%04d-12-31', $year));
        $daysElapsed = $isCurrentYear
            ? (int)$yearStart->diff($now)->days + 1
            : (int)$yearStart->diff($yearEnd)->days + 1;

        $busiestMonth = (int)array_search(max($months), $months, true);
        $maxHour = max($hours) ?: 1;

        $hourBars = [];
        foreach ($hours as $h => $n) {
            $hourBars[] = [
                'hour' => $h,
                'count' => $n,
                'height' => max($n > 0 ? 6 : 2, (int)round($n / $maxHour * 100)),
                'isNight' => $h >= 22 || $h < 6,
                'label' => sprintf('%02d–%02d Uhr: %d %s', $h, ($h + 1) % 24, $n, $n === 1 ? 'Einsatz' : 'Einsätze'),
            ];
        }

        return [
            'year' => $year,
            'isCurrentYear' => $isCurrentYear,
            'count' => $count,
            'hours' => intdiv($totalMinutes, 60),
            'minutesRest' => $totalMinutes % 60,
            'everyDays' => number_format($daysElapsed / $count, 1, ',', ''),
            'nightPercent' => (int)round($night / $count * 100),
            'weekendPercent' => (int)round($weekend / $count * 100),
            'busiestMonth' => self::MONTHS_LONG[$busiestMonth] ?? '',
            'busiestMonthCount' => $months[$busiestMonth] ?? 0,
            'lastTimestamp' => $last->getStart()->getTimestamp(),
            'lastTitle' => $last->getTitle(),
            'lastUid' => (int)$last->getUid(),
            'weeks' => $this->buildWeeks($year, $byDay, $now),
            'monthLabels' => $this->buildMonthLabels($year),
            'hourBars' => $hourBars,
            'legend' => array_values($legend),
        ];
    }

    /**
     * @return array{title:string,color:string}
     */
    private function categoryOf(Event $event): array
    {
        foreach ($event->getTypes() as $type) {
            if (!is_object($type) || !method_exists($type, 'getCategory')) {
                continue;
            }
            $category = $type->getCategory();
            if ($category !== null) {
                $color = trim((string)$category->getColor());
                return [
                    'title' => $category->getTitle() !== '' ? $category->getTitle() : 'Sonstige',
                    'color' => preg_match('/^#?[0-9a-fA-F]{3,8}$/', $color)
                        ? '#' . ltrim($color, '#')
                        : self::FALLBACK_COLOR,
                ];
            }
        }
        return ['title' => 'Sonstige', 'color' => '#7a8089'];
    }

    /**
     * Wochen-Spalten (Mo–So), beginnend mit der Woche, die den 1. Januar enthält.
     *
     * @param array<string,array<int,array<string,mixed>>> $byDay
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function buildWeeks(int $year, array $byDay, \DateTimeImmutable $now): array
    {
        $first = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $cursor = $first->modify('-' . ((int)$first->format('N') - 1) . ' days');
        $last = new \DateTimeImmutable(sprintf('%04d-12-31', $year));
        $today = $now->format('Y-m-d');

        $weeks = [];
        while ($cursor <= $last) {
            $week = [];
            for ($d = 0; $d < 7; $d++) {
                $key = $cursor->format('Y-m-d');
                $inYear = (int)$cursor->format('Y') === $year;
                $dayEvents = $byDay[$key] ?? [];
                $cell = [
                    'inYear' => $inYear,
                    'isFuture' => $inYear && $key > $today,
                    'isToday' => $key === $today,
                    'count' => count($dayEvents),
                    'color' => '',
                    'anchorUid' => 0,
                    'label' => $cursor->format('d.m.Y') . ' · kein Einsatz',
                    'multi' => count($dayEvents) > 1,
                ];
                if ($dayEvents !== []) {
                    $titles = array_map(
                        static fn(array $e): string => $e['event']->getStart()->format('H:i') . ' ' . $e['event']->getTitle(),
                        $dayEvents
                    );
                    $cell['color'] = $dayEvents[0]['category']['color'];
                    $cell['anchorUid'] = (int)$dayEvents[0]['event']->getUid();
                    $cell['label'] = $cursor->format('d.m.Y') . ' · ' . implode(' | ', $titles);
                }
                $week[] = $cell;
                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $week;
        }
        return $weeks;
    }

    /**
     * Monatsbeschriftungen mit Spaltenindex (1-basiert, für CSS grid-column).
     *
     * @return array<int,array{label:string,column:int}>
     */
    private function buildMonthLabels(int $year): array
    {
        $first = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
        $gridStart = $first->modify('-' . ((int)$first->format('N') - 1) . ' days');
        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $date = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $m));
            $labels[] = [
                'label' => self::MONTHS[$m],
                'column' => intdiv((int)$gridStart->diff($date)->days, 7) + 1,
            ];
        }
        return $labels;
    }
}
