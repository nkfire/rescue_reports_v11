<?php
namespace Nkfire\RescueReports\Hooks;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class VehicleNameAutoFill
{
    public function processDatamap_afterDatabaseOperations($status, $table, $id, array &$fieldArray, DataHandler $pObj): void
    {
        if ($table === 'tx_rescuereports_domain_model_vehicle' && $status === 'new') {
            $realUid = $pObj->substNEWwithIDs[$id] ?? 0;
            if (!empty($fieldArray['car']) && $realUid > 0) {
                $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable('tx_rescuereports_domain_model_car');
                $carRow = $connection->select(
                    ['name'],
                    'tx_rescuereports_domain_model_car',
                    ['uid' => (int)$fieldArray['car']]
                )->fetchAssociative();

                if (!empty($carRow['name'])) {
                    GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getConnectionForTable('tx_rescuereports_domain_model_vehicle')
                        ->update(
                            'tx_rescuereports_domain_model_vehicle',
                            ['name' => $carRow['name']],
                            ['uid' => $realUid]
                        );
                }
            }
        }
    }
}
