<?php
namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CarLabelItemsProcFunc
{
    public function addOrganisationToLabel(array &$config): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_rescuereports_domain_model_car')
            ->createQueryBuilder();

        foreach ($config['items'] as &$item) {
            // Skip empty and default items
            if (empty($item[1]) || !is_numeric($item[1])) {
                continue;
            }

            $carUid = (int)$item[1];

            $carRow = $queryBuilder
                ->select('name', 'organization')
                ->from('tx_rescuereports_domain_model_car')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($carUid, \PDO::PARAM_INT))
                )
                ->executeQuery()
                ->fetchAssociative();

            if (!empty($carRow['organization'])) {
                $orgQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable('tx_rescuereports_domain_model_organisation')
                    ->createQueryBuilder();

                $orgData = $orgQueryBuilder
                    ->select('abbreviation')
                    ->from('tx_rescuereports_domain_model_organisation')
                    ->where(
                        $orgQueryBuilder->expr()->eq(
                            'uid',
                            $orgQueryBuilder->createNamedParameter((int)$carRow['organization'], \PDO::PARAM_INT)
                        )
                    )
                    ->andWhere($orgQueryBuilder->expr()->eq('deleted', 0))
                    ->andWhere($orgQueryBuilder->expr()->eq('hidden', 0))
                    ->executeQuery()
                    ->fetchAssociative();

                if (!empty($orgData['abbreviation'])) {
                    $item[0] .= ' (' . $orgData['abbreviation'] . ')';
                }
            }
        }
    }
}
