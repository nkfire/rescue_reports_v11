<?php
namespace Nkfire\RescueReports\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

class Event extends AbstractEntity
{
    protected string $title = '';
    protected string $description = '';
    protected ?\DateTime $start = null;
    protected ?\DateTime $end = null;
    protected string $number = '';
    protected string $location = '';
    protected ?float $latitude = null;
    protected ?float $longitude = null;

    /**
    * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Nkfire\RescueReports\Domain\Model\Vehicle>
    */
    protected ObjectStorage $vehicles;


    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\Nkfire\RescueReports\Domain\Model\Station>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Cascade("remove")
     * @TYPO3\CMS\Extbase\Annotation\ORM\Lazy
     */
    protected ObjectStorage $stations;

    /** @var ObjectStorage<FileReference> */
    protected ObjectStorage $images;

    /** @var ObjectStorage<Type> */
    protected ObjectStorage $types;

    /** @var ObjectStorage<Type> */
    protected ObjectStorage $keywordEscalation;

    protected bool $enableKeywordEscalation = false;

    public function __construct()
    {
        $this->vehicles = new ObjectStorage();
        $this->stations = new ObjectStorage();
        $this->images = new ObjectStorage();
        $this->types = new ObjectStorage();
        $this->keywordEscalation = new ObjectStorage();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getStart(): ?\DateTime
    {
        return $this->start;
    }

    public function setStart(?\DateTime $start): void
    {
        $this->start = $start;
    }

    public function getEnd(): ?\DateTime
    {
        return $this->end;
    }

    public function setEnd(?\DateTime $end): void
    {
        $this->end = $end;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getNumberShort(): string
    {
        $number = (string)$this->number;
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return substr(str_pad($digits, 3, '0', STR_PAD_LEFT), -3);
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): void
    {
        $this->location = $location;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): void
    {
        $this->latitude = $latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): void
    {
        $this->longitude = $longitude;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** @return ObjectStorage<Vehicle> */
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

    /**
     * @return ObjectStorage<Station>
     */
    public function getStations(): ObjectStorage
    {
        return $this->stations;
    }

    public function setStations(ObjectStorage $stations): void
    {
        $this->stations = $stations;
    }

    public function addStation(Station $station): void
    {
        $this->stations->attach($station);
    }

    public function removeStation(Station $station): void
    {
        $this->stations->detach($station);
    }

    /** @return ObjectStorage<FileReference> */
    public function getImages(): ObjectStorage
    {
        return $this->images;
    }

    public function setImages(ObjectStorage $images): void
    {
        $this->images = $images;
    }

    public function addImage(FileReference $image): void
    {
        $this->images->attach($image);
    }

    public function removeImage(FileReference $image): void
    {
        $this->images->detach($image);
    }

    /** @return ObjectStorage<Type> */
    public function getTypes(): ObjectStorage
    {
        return $this->types;
    }

    public function setTypes(ObjectStorage $types): void
    {
        $this->types = $types;
    }

    public function addType(Type $type): void
    {
        $this->types->attach($type);
    }

    public function removeType(Type $type): void
    {
        $this->types->detach($type);
    }

    public function getDuration(): string
    {
        if (!$this->start instanceof \DateTimeInterface || !$this->end instanceof \DateTimeInterface) {
            return '';
        }

        $diff = $this->start->diff($this->end);

        $hours = ($diff->days * 24) + $diff->h;
        $minutes = $diff->i;

        if ($hours > 0) {
            return sprintf('%d Std. %02d Min.', $hours, $minutes);
        }

        return sprintf('%d Min.', $minutes);
    }

    protected string $slug = '';

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }
    protected string $internalNotes = '';

    public function getInternalNotes(): string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(string $internalNotes): void
    {
        $this->internalNotes = $internalNotes;
    }

    protected bool $disableDetail = false;
    public function isDisableDetail(): bool
    {
        return $this->disableDetail;
    }
    public function setDisableDetail(bool $disableDetail): void
    {
        $this->disableDetail = $disableDetail;
    }

    /** @return ObjectStorage<Type> */
    public function getKeywordEscalation(): ObjectStorage
    {
        return $this->keywordEscalation;
    }

    public function setKeywordEscalation(ObjectStorage $keywordEscalation): void
    {
        $this->keywordEscalation = $keywordEscalation;
    }

    public function addKeywordEscalation(Type $type): void
    {
        $this->keywordEscalation->attach($type);
    }

    public function removeKeywordEscalation(Type $type): void
    {
        $this->keywordEscalation->detach($type);
    }

    public function getEnableKeywordEscalation(): bool
    {
        return $this->enableKeywordEscalation;
    }

    public function setEnableKeywordEscalation(bool $enableKeywordEscalation): void
    {
        $this->enableKeywordEscalation = $enableKeywordEscalation;
    }
}