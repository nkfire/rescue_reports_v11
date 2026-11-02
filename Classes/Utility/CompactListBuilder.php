<?php
declare(strict_types=1);

namespace Nkfire\RescueReports\Utility;

use Nkfire\RescueReports\Domain\Model\Event;

/**
 * Bereitet die Event-Items für die Kompaktliste auf:
 * Kategorie (Farbe/Titel), Nacht-Kennzeichen, Monatsgruppen und Filter-Chips.
 * Kein Datenbankzugriff – arbeitet nur auf den bereits geladenen Items.
 */
class CompactListBuilder
{
    private const FALLBACK = ['uid' => 0, 'title' => 'Sonstige', 'color' => '#7a8089', 'ink' => '#fff'];

    private const MONTHS = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
        7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];

    private const MONTHS_SHORT = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez',
    ];

    private const WEEKDAYS = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];

    /**
     * @param array<int,array<string,mixed>> $eventItems
     * @param array<int|string,array<int|string,mixed>> $statisticsByYear Jahr => Statistik-Array (für Inline-Statistik je Jahr)
     * @param bool $groupByYear Jahresüberschriften ausgeben ("Alle Jahre")
     * @return array{years: array<int,array<string,mixed>>, categories: array<int,array<string,mixed>>, total: int, nightCount: int}
     */
    public function build(array $eventItems, array $statisticsByYear = [], bool $groupByYear = false): array
    {
        $years = [];
        $categories = [];
        $total = 0;
        $nightCount = 0;

        foreach ($eventItems as $item) {
            $event = $item['event'] ?? null;
            if (!$event instanceof Event) {
                continue;
            }
            $start = $event->getStart();
            $category = $this->categoryOf($event);
            $catKey = 'c' . $category['uid'];

            $row = $item;
            $row['category'] = $category;
            $row['categoryKey'] = $catKey;
            $row['keyword'] = $this->keywordOf($event);
            $row['isNight'] = false;
            $row['day'] = '';
            $row['monthShort'] = '';
            $row['weekday'] = '';

            $yearKey = 'Unbekannt';
            $monthKey = 'unbekannt';
            $monthLabel = 'Ohne Datum';

            if ($start instanceof \DateTimeInterface) {
                $hour = (int)$start->format('G');
                $row['isNight'] = $hour >= 22 || $hour < 6;
                $nightCount += $row['isNight'] ? 1 : 0;
                $row['day'] = $start->format('d');
                $row['monthShort'] = self::MONTHS_SHORT[(int)$start->format('n')];
                $row['weekday'] = self::WEEKDAYS[(int)$start->format('N')];
                $yearKey = $start->format('Y');
                $monthKey = $start->format('Y-m');
                $monthLabel = self::MONTHS[(int)$start->format('n')] . ' ' . $start->format('Y');
            }

            if (!isset($years[$yearKey])) {
                $years[$yearKey] = [
                    'year' => $yearKey,
                    'statistics' => isset($statisticsByYear[$yearKey])
                        ? [(int)$yearKey => $statisticsByYear[$yearKey]]
                        : [],
                    'months' => [],
                ];
            }
            if (!isset($years[$yearKey]['months'][$monthKey])) {
                $years[$yearKey]['months'][$monthKey] = [
                    'key' => $monthKey,
                    'label' => $monthLabel,
                    'count' => 0,
                    'items' => [],
                ];
            }
            $years[$yearKey]['months'][$monthKey]['items'][] = $row;
            $years[$yearKey]['months'][$monthKey]['count']++;

            if (!isset($categories[$catKey])) {
                $categories[$catKey] = $category + ['key' => $catKey, 'count' => 0];
            }
            $categories[$catKey]['count']++;
            $total++;
        }

        if (!$groupByYear) {
            // Alles in eine Gruppe ohne Jahresüberschrift zusammenführen
            $merged = ['year' => '', 'statistics' => [], 'months' => []];
            foreach ($years as $year) {
                $merged['months'] += $year['months'];
            }
            $years = $merged['months'] === [] ? [] : [$merged];
        }

        foreach ($years as &$year) {
            $year['months'] = array_values($year['months']);
        }
        unset($year);

        usort($categories, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'years' => array_values($years),
            'categories' => array_values($categories),
            'total' => $total,
            'nightCount' => $nightCount,
        ];
    }

    /**
     * Alarmstichwort (Titel des ersten Einsatztyps, z. B. "TH1", "BMA").
     */
    private function keywordOf(Event $event): string
    {
        foreach ($event->getTypes() as $type) {
            if (is_object($type) && method_exists($type, 'getTitle')) {
                $title = trim((string)$type->getTitle());
                if ($title !== '') {
                    return $title;
                }
            }
        }
        return '';
    }

    /**
     * @return array{uid:int,title:string,color:string,ink:string}
     */
    private function categoryOf(Event $event): array
    {
        foreach ($event->getTypes() as $type) {
            if (!is_object($type) || !method_exists($type, 'getCategory')) {
                continue;
            }
            $category = $type->getCategory();
            if ($category === null) {
                continue;
            }
            $color = trim((string)$category->getColor());
            $color = preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color) ? '#' . ltrim($color, '#') : '#c8102e';
            return [
                'uid' => (int)$category->getUid(),
                'title' => $category->getTitle() !== '' ? $category->getTitle() : self::FALLBACK['title'],
                'color' => $color,
                'ink' => $this->inkFor($color),
            ];
        }
        return self::FALLBACK;
    }

    /**
     * Lesbare Schriftfarbe (schwarz/weiß) für Text auf der Kategoriefarbe.
     */
    private function inkFor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $channel = static function (string $part): float {
            $c = hexdec($part) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $channel(substr($hex, 0, 2))
            + 0.7152 * $channel(substr($hex, 2, 2))
            + 0.0722 * $channel(substr($hex, 4, 2));
        // Kontrast gegen Weiß vs. gegen Schwarz vergleichen
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#fff' : '#1d1f23';
    }
}
