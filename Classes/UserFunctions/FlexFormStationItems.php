<?php
declare(strict_types=1);

namespace Nkfire\RescueReports\UserFunctions;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FlexFormStationItems
{
    public function addPrimaryStations(array &$config): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_rescuereports_domain_model_station');

        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder->expr()->eq(
            'station.exclude_from_filter',
            $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
        );

        $rows = $queryBuilder
            ->select(
                'station.uid',
                'station.name',
                'brigade.name AS brigade_name'
            )
            ->from('tx_rescuereports_domain_model_station', 'station')
            ->innerJoin(
                'station',
                'tx_rescuereports_domain_model_brigade',
                'brigade',
                $queryBuilder->expr()->eq(
                    'station.brigade',
                    $queryBuilder->quoteIdentifier('brigade.uid')
                )
            )
            ->where(
                $queryBuilder->expr()->eq(
                    'station.deleted',
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'station.hidden',
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'brigade.deleted',
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'brigade.hidden',
                    $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'brigade.is_primary',
                    $queryBuilder->createNamedParameter(1, \PDO::PARAM_INT)
                )
            )
            ->orderBy('brigade.name', 'ASC')
            ->addOrderBy('station.sorting', 'ASC')
            ->addOrderBy('station.name', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $label = $row['station.name'] ?? $row['name'] ?? '';
            $brigadeName = $row['brigade_name'] ?? '';

            if ($brigadeName !== '') {
                $label .= ' (' . $brigadeName . ')';
            }

            $config['items'][] = [
                $label,
                (int)$row['uid'],
            ];
        }
    }
}