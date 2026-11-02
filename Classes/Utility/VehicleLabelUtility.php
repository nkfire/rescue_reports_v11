<?php
namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class VehicleLabelUtility
{
    public function getCustomLabel(array &$params, $ref = null): void
    {
        $row = $params['row'];

        $vehicleName = $row['name'] ?? '';
        $orgAbbr = '';

        if (!empty($row['car'])) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_rescuereports_domain_model_car')
                ->createQueryBuilder();

            $carRow = $queryBuilder
                ->select('organization', 'name')
                ->from('tx_rescuereports_domain_model_car')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$row['car'], \PDO::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!empty($carRow['organization'])) {
                $orgQuery = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable('tx_rescuereports_domain_model_organisation')
                    ->createQueryBuilder();

                $orgRow = $orgQuery
                    ->select('abbreviation')
                    ->from('tx_rescuereports_domain_model_organisation')
                    ->where(
                        $orgQuery->expr()->eq('uid', $orgQuery->createNamedParameter((int)$carRow['organization'], \PDO::PARAM_INT))
                    )
                    ->executeQuery()
                    ->fetchAssociative();

                if (!empty($orgRow['abbreviation'])) {
                    $orgAbbr = $orgRow['abbreviation'];
                }
            }
        }

        $params['title'] = $orgAbbr ? $vehicleName . ' (' . $orgAbbr . ')' : $vehicleName;
    }
}
