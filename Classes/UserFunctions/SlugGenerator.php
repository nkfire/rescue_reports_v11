<?php
declare(strict_types=1);

namespace Nkfire\RescueReports\UserFunctions;

class SlugGenerator
{
    public function generate(array $params): string
    {
        $record = $params['record'];

        $title = $record['title'] ?? '';
        $start = $record['start'] ?? '';

        if (empty($start)) {
            return $this->slugify($title);
        }

        $date = strtotime($start);

        $month = date('m', $date);
        $day = date('d', $date);

        return $month . '/' . $day . '/' . $this->slugify($title);
    }

    protected function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        return trim($text, '-');
    }
}