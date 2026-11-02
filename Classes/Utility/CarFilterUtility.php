<?php
namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class CarFilterUtility
{
    public function filterBySelectedStations(array &$config)
    {
        $selectedStationUids = $config['row']['stations'] ?? [];
        if (!is_array($selectedStationUids)) {
            $selectedStationUids = GeneralUtility::intExplode(',', $selectedStationUids, true);
        }

        $config['items'] = [];

        $alreadyAddedCarUids = [];

        if (!empty($selectedStationUids)) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_rescuereports_station_car_mm');

            $rows = $queryBuilder
                ->select('uid_local', 'uid_foreign')
                ->from('tx_rescuereports_station_car_mm')
                ->where(
                    $queryBuilder->expr()->in(
                        'uid_local',
                        $queryBuilder->createNamedParameter($selectedStationUids, \TYPO3\CMS\Core\Database\Connection::PARAM_INT_ARRAY)
                    )
                )
                ->executeQuery()
                ->fetchAllAssociative();

            $grouped = [];
            foreach ($rows as $row) {
                $stationUid = (int)$row['uid_local'];
                $carUid = (int)$row['uid_foreign'];

                $stationQuery = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tx_rescuereports_domain_model_station');
                $station = $stationQuery
                    ->select('uid', 'name', 'brigade', 'sorting')
                    ->from('tx_rescuereports_domain_model_station')
                    ->where(
                        $stationQuery->expr()->eq('uid', $stationQuery->createNamedParameter($stationUid, \PDO::PARAM_INT))
                    )
                    ->executeQuery()
                    ->fetchAssociative();

                $brigadeName = '';
                $brigadePriority = 9999;
                $brigadeUid = 0;
                if (!empty($station['brigade'])) {
                    $brigadeQuery = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getQueryBuilderForTable('tx_rescuereports_domain_model_brigade');
                    $brigade = $brigadeQuery
                        ->select('uid', 'name', 'sorting')
                        ->from('tx_rescuereports_domain_model_brigade')
                        ->where(
                            $brigadeQuery->expr()->eq('uid', $brigadeQuery->createNamedParameter($station['brigade'], \PDO::PARAM_INT))
                        )
                        ->executeQuery()
                        ->fetchAssociative();
                    $brigadeName = $brigade['name'] ?? '';
                    $brigadePriority = isset($brigade['sorting']) ? (int)$brigade['sorting'] : 9999;
                    $brigadeUid = (int)($brigade['uid'] ?? 0);
                }

                $carQuery = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tx_rescuereports_domain_model_car');
                $car = $carQuery
                    ->select('uid', 'name')
                    ->from('tx_rescuereports_domain_model_car')
                    ->where(
                        $carQuery->expr()->eq('uid', $carQuery->createNamedParameter($carUid, \PDO::PARAM_INT))
                    )
                    ->executeQuery()
                    ->fetchAssociative();

                if ($car && $station && $brigadeName) {
                    $label = '  🚒 ' . $car['name'];
                    $value = $car['uid'];
                    $alreadyAddedCarUids[] = $car['uid'];
                    $grouped[$brigadeUid]['priority'] = $brigadePriority;
                    $grouped[$brigadeUid]['name'] = $brigadeName;
                    $grouped[$brigadeUid]['stations'][$stationUid]['name'] = $station['name'];
                    $grouped[$brigadeUid]['stations'][$stationUid]['sorting'] = $station['sorting'];
                    $grouped[$brigadeUid]['stations'][$stationUid]['cars'][] = [$label, $value];
                }
            }

            uasort($grouped, function($a, $b) {
                return $a['priority'] <=> $b['priority'];
            });

            foreach ($grouped as $brigadeArr) {
                if (trim($brigadeArr['name']) !== '') {
                    $config['items'][] = [
                        '🏘️ ' . $brigadeArr['name'],
                        null,
                        null,
                        null,
                        'divider'
                    ];
                }
                $stations = $brigadeArr['stations'];
                uasort($stations, function($a, $b) {
                    return ($a['sorting'] ?? 9999) <=> ($b['sorting'] ?? 9999);
                });
                foreach ($stations as $station) {
                    if (!empty($station['name'])) {
                        $config['items'][] = [
                            '📍 ' . $station['name'],
                            null,
                            null,
                            null,
                            'divider'
                        ];
                    }
                    usort($station['cars'], function($a, $b) {
                        return strnatcasecmp($a[0], $b[0]);
                    });
                    foreach ($station['cars'] as $item) {
                        $config['items'][] = [$item[0], $item[1]];
                    }
                }
            }
        }

        // --- Ergänzung: Bereits gespeicherte Fahrzeuge immer anzeigen ---
        // Hole alle aktuell gespeicherten Fahrzeuge aus dem Datensatz
        $selectedCarUids = [];
        if (!empty($config['row']['cars'])) {
            if (is_array($config['row']['cars'])) {
                $selectedCarUids = $config['row']['cars'];
            } else {
                $selectedCarUids = GeneralUtility::intExplode(',', $config['row']['cars'], true);
            }
        }

        // Füge alle gespeicherten Fahrzeuge hinzu, falls sie nicht schon in der Liste sind
        foreach ($selectedCarUids as $carUid) {
            if (!in_array($carUid, $alreadyAddedCarUids)) {
                // Hole Fahrzeugdaten aus DB
                $carQuery = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tx_rescuereports_domain_model_car');
                $car = $carQuery
                    ->select('uid', 'name')
                    ->from('tx_rescuereports_domain_model_car')
                    ->where(
                        $carQuery->expr()->eq('uid', $carQuery->createNamedParameter($carUid, \PDO::PARAM_INT))
                    )
                    ->executeQuery()
                    ->fetchAssociative();
                if ($car) {
                    $label = '  🚒 ' . $car['name'] . ' (nicht mehr auswählbar)';
                    $value = $car['uid'];
                    $config['items'][] = [$label, $value];
                }
            }
        }

        // Optional: sortieren, falls nötig
        // usort($config['items'], function($a, $b) { return strnatcasecmp($a[0], $b[0]); });
    }
}