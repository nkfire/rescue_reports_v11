<?php

namespace Nkfire\RescueReports\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Station extends AbstractEntity
{
    protected string $name = '';

    protected ?Brigade $brigade = null;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Nkfire\RescueReports\Domain\Model\Vehicle>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Cascade("remove")
     * @TYPO3\CMS\Extbase\Annotation\ORM\Lazy
     */
    protected ObjectStorage $vehicles;

    public function __construct()
    {
        $this->vehicles = new ObjectStorage();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBrigade(): ?Brigade
    {
        return $this->brigade;
    }

    public function setBrigade(?Brigade $brigade): void
    {
        $this->brigade = $brigade;
    }

    /**
     * @return ObjectStorage<Vehicle>
     */
    public function getVehicles(): ObjectStorage
    {
        return $this->vehicles;
    }

    public function setVehicles(ObjectStorage $vehicles): void
    {
        $this->vehicles = $vehicles;
    }

    public function addVehicle(Vehicle $vehicle): void
    {
        $this->vehicles->attach($vehicle);
    }

    public function removeVehicle(Vehicle $vehicle): void
    {
        $this->vehicles->detach($vehicle);
    }
    protected int $sorting = 9999;

    public function getSorting(): int
    {
        return $this->sorting;
    }

    public function setSorting(int $sorting): void
    {
        $this->sorting = $sorting;
    }
    protected string $prefix = '';

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }
    protected bool $excludeFromFilter = false;
    public function isExcludeFromFilter(): bool
    {
        return $this->excludeFromFilter;
    }
    public function setExcludeFromFilter(bool $excludeFromFilter): void
    {
        $this->excludeFromFilter = $excludeFromFilter;
    }
}