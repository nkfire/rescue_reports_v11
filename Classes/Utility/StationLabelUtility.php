<?php

namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class StationLabelUtility
{
    public function addGroupedStations(array &$config): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_domain_model_station');

        $queryBuilder = $connection->createQueryBuilder();
        $stations = $queryBuilder
            ->select('uid', 'name', 'brigade')
            ->from('tx_rescuereports_domain_model_station')
            ->executeQuery()
            ->fetchAllAssociative();

        $brigadeData = $this->getBrigadeData();

        $grouped = [];

        foreach ($stations as $station) {
            $brigadeId = (int)($station['brigade'] ?? 0);
            $brigadeName = $brigadeData[$brigadeId]['name'] ?? 'Unbekannt';
            $sorting = $brigadeData[$brigadeId]['sorting'] ?? 9999;

            $key = str_pad($sorting, 6, '0', STR_PAD_LEFT) . '_' . $brigadeName;
            $grouped[$key][] = [$station['name'], $station['uid']];
        }

        ksort($grouped); // sort by priority+name

        foreach ($grouped as $label => $items) {
            usort($items, fn($a, $b) => strcasecmp($a[0], $b[0]));
            $config['items'][] = [explode('_', $label, 2)[1], '--div--'];
            foreach ($items as $item) {
                $config['items'][] = $item;
            }
        }
    }

    protected function getBrigadeData(): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_domain_model_brigade');

        $queryBuilder = $connection->createQueryBuilder();
        $rows = $queryBuilder
            ->select('uid', 'name', 'sorting')
            ->from('tx_rescuereports_domain_model_brigade')
            ->executeQuery()
            ->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['uid']] = [
                'name' => $row['name'],
                'sorting' => (int)$row['sorting'],
            ];
        }
        return $result;
    }
}