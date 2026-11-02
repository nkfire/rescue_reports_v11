<?php
namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

class CarLabelUtility
{
    public function getCustomLabel(array &$params, $ref = null): void
    {
        $row = $params['row'];

        $carName = $row['name'] ?? '';
        $orgAbbr = '';

        if (!empty($row['organization'])) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_rescuereports_domain_model_organisation')
                ->createQueryBuilder();

            $orgRow = $queryBuilder
                ->select('abbreviation')
                ->from('tx_rescuereports_domain_model_organisation')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$row['organization'], \PDO::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!empty($orgRow['abbreviation'])) {
                $orgAbbr = $orgRow['abbreviation'];
            }
        }

        $params['title'] = $orgAbbr ? $carName . ' (' . $orgAbbr . ')' : $carName;
    }
}