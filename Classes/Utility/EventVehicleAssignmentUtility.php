<?php
namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class EventVehicleAssignmentUtility
{
    public static function getAssignmentOptions(array &$config): void
    {
        $eventUid = (int)($config['row']['uid'] ?? 0);
        if ($eventUid <= 0) {
            return;
        }

        $utility = new self();
        $stationUids = $utility->getRelatedStationUids($eventUid);
        if (empty($stationUids)) {
            return;
        }

        $vehicles = $utility->getVehiclesWithStationName($stationUids);

        foreach ($vehicles as $vehicle) {
            $label = $vehicle['station_name'] . ' – ' . $vehicle['car_name'];
            $config['items'][] = [$label, $vehicle['car_uid']];
        }
    }

    public function getRelatedStationUids(int $eventUid): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_event_station_mm');

        return $connection->createQueryBuilder()
            ->select('uid_foreign')
            ->from('tx_rescuereports_event_station_mm')
            ->where('uid_local = :uid')
            ->setParameter(':uid', $eventUid, \PDO::PARAM_INT)
            ->executeQuery()
            ->fetchFirstColumn();
    }

    public function getVehiclesWithStationName(array $stationUids): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_station_car_mm');

        $queryBuilder = $connection->createQueryBuilder();
        $rows = $queryBuilder
            ->select('c.uid AS car_uid', 's.name AS station_name', 'c.name AS car_name')
            ->from('tx_rescuereports_station_car_mm', 'sc')
            ->innerJoin('sc', 'tx_rescuereports_domain_model_station', 's', 's.uid = sc.uid_local')
            ->innerJoin('sc', 'tx_rescuereports_domain_model_car', 'c', 'c.uid = sc.uid_foreign')
            ->where(
                $queryBuilder->expr()->in('sc.uid_local', $queryBuilder->createNamedParameter($stationUids, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY))
            )
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }
}