<?php
namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\DimensionMapping;
use Crossmedia\Fourallportal\Domain\Model\Module;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;

interface ValueSetterInterface
{
    public function setValueOnObject(
        $value,
        string $sourcePropertyName,
        array $inputData,
        DomainObjectInterface|FileInterface $object,
        Module $module,
        MappingInterface $mappingClass,
        DimensionMapping|null $dimensionMapping = null
    ): void;
}
