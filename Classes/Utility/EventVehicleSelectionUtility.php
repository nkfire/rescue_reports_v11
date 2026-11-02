<?php
namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class EventVehicleSelectionUtility
{
    public function getAvailableVehicles(array &$config): void
    {
        $eventRow = $config['row'] ?? [];

        if (empty($eventRow['stations'])) {
            return;
        }

        $stationField = $eventRow['stations'];
        $stationIds = is_array($stationField)
            ? array_map('intval', $stationField)
            : GeneralUtility::intExplode(',', $stationField, true);

        if (empty($stationIds)) {
            return;
        }

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_domain_model_vehicle');

        $queryBuilder = $connection->createQueryBuilder();

        $vehicles = $queryBuilder
            ->select('v.uid', 'v.name', 's.name AS station_name', 's.sorting AS station_sorting', 'b.name AS brigade_name', 'b.sorting AS brigade_sorting')
            ->from('tx_rescuereports_domain_model_vehicle', 'v')
            ->innerJoin('v', 'tx_rescuereports_domain_model_station', 's', 'v.station = s.uid')
            ->leftJoin('s', 'tx_rescuereports_domain_model_brigade', 'b', 's.brigade = b.uid')
            ->where(
                $queryBuilder->expr()->in('v.station', $queryBuilder->createNamedParameter($stationIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY))
            )
            ->orderBy('b.sorting')
            ->addOrderBy('station_sorting')
            ->addOrderBy('v.name')
            ->executeQuery()
            ->fetchAllAssociative();

        $grouped = [];

        foreach ($vehicles as $vehicle) {
            $groupLabel = str_pad((int)$vehicle['brigade_sorting'], 6, '0', STR_PAD_LEFT) . '_' . ($vehicle['brigade_name'] ?? 'Unbekannt');
            $itemLabel = $vehicle['station_name'] . ' – ' . $vehicle['name'];
            $grouped[$groupLabel][] = [$itemLabel, (int)$vehicle['uid']];
        }

        ksort($grouped);

        foreach ($grouped as $groupLabel => $items) {
            $config['items'][] = [explode('_', $groupLabel, 2)[1], '--div--'];
            foreach ($items as $item) {
                $config['items'][] = $item;
            }
        }

        // Bereits gewählte Fahrzeuge zusätzlich einfügen
        $alreadySelectedIds = array_unique(array_column($config['itemArray'] ?? [], 1));
        if (!empty($alreadySelectedIds)) {
            $existing = $connection->createQueryBuilder()
                ->select('v.uid', 'v.name', 's.name AS station_name', 'b.name AS brigade_name')
                ->from('tx_rescuereports_domain_model_vehicle', 'v')
                ->innerJoin('v', 'tx_rescuereports_domain_model_station', 's', 'v.station = s.uid')
                ->leftJoin('s', 'tx_rescuereports_domain_model_brigade', 'b', 's.brigade = b.uid')
                ->where(
                    $queryBuilder->expr()->in('v.uid', $queryBuilder->createNamedParameter($alreadySelectedIds, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY))
                )
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($existing as $vehicle) {
                $label = $vehicle['station_name'] . ' – ' . $vehicle['name'];
                $config['items'][] = [$label, (int)$vehicle['uid']];
            }
        }
    }
}