<?php

namespace Nkfire\RescueReports\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Deployment extends AbstractEntity {
    protected ?Event $event = null;
    protected ?Brigade $brigade = null;
    /** @var ObjectStorage<Station> */
    protected ObjectStorage $stations;

    public function __construct() {
        $this->stations = new ObjectStorage();
    }
    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $event): void { $this->event = $event; }
    public function getBrigade(): ?Brigade { return $this->brigade; }
    public function setBrigade(?Brigade $brigade): void { $this->brigade = $brigade; }
    public function getStations(): ObjectStorage { return $this->stations; }
    public function setStations(ObjectStorage $stations): void { $this->stations = $stations; }
    public function addStation(Station $station): void { $this->stations->attach($station); }
    public function removeStation(Station $station): void { $this->stations->detach($station); }
}