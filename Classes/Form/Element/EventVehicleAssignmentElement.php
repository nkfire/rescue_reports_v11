<?php
namespace Nkfire\RescueReports\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class EventVehicleAssignmentElement extends AbstractFormElement
{
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();

        $eventUid = (int)($this->data['databaseRow']['uid'] ?? 0);

        $vehicles = [];

        if ($eventUid > 0) {
            $stationUids = $this->getRelatedStationUids($eventUid);
            if (!empty($stationUids)) {
                $vehicles = $this->getVehiclesWithStationName($stationUids);
            }
        }

        // baue die Feldoptionen
        $options = [];
        foreach ($vehicles as $vehicle) {
            $label = $vehicle['station_name'] . ' – ' . $vehicle['car_name'];
            $options[] = [
                'label' => $label,
                'value' => $vehicle['car_uid'],
            ];
        }

        $resultArray['options'] = $options;

        return $resultArray;
    }

    protected function getRelatedStationUids(int $eventUid): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_event_station_mm');

        return $connection->createQueryBuilder()
            ->select('uid_foreign')
            ->from('tx_rescuereports_event_station_mm')
            ->where('uid_local = :uid')
            ->setParameter(':uid', $eventUid)
            ->executeQuery()
            ->fetchFirstColumn();
    }

    protected function getVehiclesWithStationName(array $stationUids): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_station_car_mm');

        return $connection->createQueryBuilder()
            ->select('c.uid AS car_uid', 's.name AS station_name', 'c.name AS car_name')
            ->from('tx_rescuereports_station_car_mm', 'sc')
            ->innerJoin('sc', 'tx_rescuereports_domain_model_station', 's', 's.uid = sc.uid_local')
            ->innerJoin('sc', 'tx_rescuereports_domain_model_car', 'c', 'c.uid = sc.uid_foreign')
            ->where(
                $connection->createQueryBuilder()->expr()->in('sc.uid_local', $stationUids)
            )
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }
}