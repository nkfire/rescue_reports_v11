<?php

namespace Nkfire\RescueReports\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Annotation\ORM\Transient;

class Brigade extends AbstractEntity
{
    protected string $name = '';
    protected int $sorting = 9999;

    /**
     * @var ObjectStorage<\Nkfire\RescueReports\Domain\Model\Station>
     * @Transient
     */
    protected ObjectStorage $stations;

    public function __construct()
    {
        $this->stations = new ObjectStorage();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSorting(): int
    {
        return $this->sorting;
    }

    public function setSorting(int $sorting): void
    {
        $this->sorting = $sorting;
    }

    public function getStations(): ObjectStorage
    {
        return $this->stations;
    }

    public function setStations(ObjectStorage $stations): void
    {
        $this->stations = $stations;
    }
    protected bool $isPrimary = false;

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): void
    {
        $this->isPrimary = $isPrimary;
    }
    
}
