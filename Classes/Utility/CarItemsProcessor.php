<?php

namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class CarItemsProcessor
{
    public function filterByStations(array &$config)
    {
        $stationUids = GeneralUtility::intExplode(',', $config['row']['station'], true);

        if (!empty($stationUids)) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_rescuereports_domain_model_car_station_mm');
            $result = $queryBuilder
                ->select('uid_foreign')
                ->from('tx_rescuereports_domain_model_car_station_mm')
                ->where(
                    $queryBuilder->expr()->in('uid_local', $queryBuilder->createNamedParameter($stationUids, Connection::PARAM_INT_ARRAY))
                )
                ->executeQuery();

            $carUids = array_unique(array_column($result->fetchAllAssociative(), 'uid_foreign'));

            if (!empty($carUids)) {
                $carQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('tx_rescuereports_domain_model_car');
                $carResult = $carQueryBuilder
                    ->select('uid', 'title')
                    ->from('tx_rescuereports_domain_model_car')
                    ->where(
                        $carQueryBuilder->expr()->in('uid', $carQueryBuilder->createNamedParameter($carUids, Connection::PARAM_INT_ARRAY))
                    )
                    ->executeQuery();

                $config['items'] = [];
                while ($row = $carResult->fetchAssociative()) {
                    $config['items'][] = [$row['title'], $row['uid']];
                }
            }
        }
    }
}
