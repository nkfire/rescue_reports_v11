<?php
namespace Nkfire\RescueReports\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use Nkfire\RescueReports\Domain\Model\Organisation;

class Car extends AbstractEntity
{
    protected string $name = '';

    /**
     * @var Organisation|null
     */
    protected $organization = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getOrganization(): ?Organisation
    {
        return $this->organization;
    }

    public function setOrganization(?Organisation $organization): void
    {
        $this->organization = $organization;
    }
}
