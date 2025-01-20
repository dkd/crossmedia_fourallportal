<?php

namespace Crossmedia\Fourallportal\TypeConverter;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Property\TypeConverterInterface;

/**
 * Interface PimBasedTypeConverterInterface
 */
interface PimBasedTypeConverterInterface extends TypeConverterInterface
{
    /**
     * @param AbstractEntity $object
     * @param string $propertyName
     * @return mixed
     */
    public function setParentObjectAndProperty(AbstractEntity $object, string $propertyName): void;
}
