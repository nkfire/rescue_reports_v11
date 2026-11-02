<?php
namespace Nkfire\RescueReports\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;

class BrigadeRepository extends Repository
{
    protected $defaultOrderings = [
        'sorting' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
        'name' => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING,
    ];
}