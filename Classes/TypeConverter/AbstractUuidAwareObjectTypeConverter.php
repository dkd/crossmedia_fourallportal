<?php

namespace Crossmedia\Fourallportal\TypeConverter;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\CMS\Extbase\Property\TypeConverterInterface;

abstract class AbstractUuidAwareObjectTypeConverter extends PersistentObjectConverter implements TypeConverterInterface, PimBasedTypeConverterInterface
{
    /**
     * @var AbstractEntity
     */
    protected AbstractEntity $parent;

    /**
     * @var string
     */
    protected string $propertyName;

    /**
     * Converters convert from strings (UUIDs) and integers (UIDs).
     * Contrary to the generic persisted object converter, this type
     * of converter does not support arrays as input (neither full
     * property arrays nor arrays containing __identity). When an
     * array is received the generic converter will be used instead.
     *
     * @var array<string>
     * @api
     */
    protected $sourceTypes = [
        'string',
        'integer'
    ];

    /**
     * @param AbstractEntity $object
     * @param string $propertyName
     * @return void
     */
    public function setParentObjectAndProperty(AbstractEntity $object, string $propertyName): void
    {
        $this->parent = $object;
        $this->propertyName = $propertyName;
    }

    /**
     * @param mixed $source
     * @param string $targetType
     * @param array $convertedChildProperties
     * @param PropertyMappingConfigurationInterface|null $configuration
     * @return object|null
     */
    public function convertFrom($source, string $targetType, array $convertedChildProperties = [], PropertyMappingConfigurationInterface $configuration = null): ?object
    {
        if (is_numeric($source)) {
            $existingRecordUid = (int)$source;
        } else {
            $tableName = $this->getTableName($targetType);
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable($tableName);
            $existingRow = $queryBuilder->select('uid')
                ->from($tableName)
                ->where(
                    $queryBuilder->expr()->eq(
                        'remote_id',
                        $queryBuilder->quote($source, \PDO::PARAM_STR)
                    )
                )
                ->executeQuery();
            try {
                $existingRecordUid = $existingRow->fetchOne()['uid'] ?? false;
            } catch (Exception $e) {
                return null;
            }
        }
        if ($existingRecordUid) {
            return $this->getObjectByUidUnrestricted((int)$existingRecordUid);
        }
        return null;
    }

    protected function getObjectByUidUnrestricted(int $uid): ?object
    {
        $query = $this->getRepository()->createQuery();
        //$query->getQuerySettings()->setLanguageMode('strict');
        //$query->getQuerySettings()->setLanguageUid($languageUid);
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setIncludeDeleted(true);
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        //$query->getQuerySettings()->setRespectSysLanguage(false);
        $query->matching($query->equals('uid', $uid));
        $object = $query->execute()->getFirst();
        if ($object) {
            $object->_memorizeCleanState();
            return $object;
        }
        return null;
    }

    protected function getTableName(string $targetType): string
    {
        return GeneralUtility::makeInstance(DataMapper::class)->getDataMap($targetType)->getTableName();
    }

    /**
     * @return RepositoryInterface
     * @see .build/vendor/typo3/cms-core/Documentation/Changelog/10.0/Breaking-87594-HardenExtbase.rst
     */
    protected function getRepository(): RepositoryInterface
    {
        return GeneralUtility::makeInstance(ltrim(str_replace('\\Domain\\Model\\', '\\Domain\\Repository\\', $this->getSupportedTargetType()) . 'Repository', '\\'));
    }

    /**
     * This type of TypeConverter has a naturally very high priority,
     * since it catches one of the same types (int) as the standard
     * generic persisted object converter.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 90;
    }
}
