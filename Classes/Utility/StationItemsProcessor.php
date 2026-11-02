<?php

namespace Nkfire\RescueReports\Utility;

use TYPO3\CMS\Backend\Form\FormDataProvider\TcaSelectItems;

class StationItemsProcessor
{
    public function filterByBrigade(array &$config)
    {
        $brigadeUid = (int)$config['row']['brigade'] ?? 0;
        if ($brigadeUid > 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tx_rescuereports_domain_model_station');
            $result = $queryBuilder
                ->select('uid', 'name')
                ->from('tx_rescuereports_domain_model_station')
                ->where(
                    $queryBuilder->expr()->eq('brigade', $queryBuilder->createNamedParameter($brigadeUid, \PDO::PARAM_INT))
                )
                ->executeQuery();

            $config['items'] = [];
            while ($row = $result->fetchAssociative()) {
                $config['items'][] = [$row['name'], $row['uid']];
            }
        }
    }
}
