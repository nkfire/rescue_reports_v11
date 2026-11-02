<?php
namespace Nkfire\RescueReports\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class StationRepository extends Repository
{
    public function findPrimaryBrigadeStations()
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        $query->matching(
            $query->logicalAnd(
                $query->equals('brigade.isPrimary', true),
                $query->equals('excludeFromFilter', false)
            )
        );

        $query->setOrderings([
            'sorting' => QueryInterface::ORDER_ASCENDING,
            'name' => QueryInterface::ORDER_ASCENDING,
        ]);

        return $query->execute();
    }
}