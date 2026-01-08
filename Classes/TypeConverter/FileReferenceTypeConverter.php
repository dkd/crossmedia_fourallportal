<?php

namespace Crossmedia\Fourallportal\TypeConverter;

use Crossmedia\Fourallportal\Mapping\DeferralException;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapFactory;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException;
use TYPO3\CMS\Extbase\Property\Exception\TargetNotFoundException;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;

class FileReferenceTypeConverter extends AbstractUuidAwareObjectTypeConverter implements PimBasedTypeConverterInterface
{
  protected $targetType = FileReference::class;
  protected AbstractEntity $parentObject;
  protected string $propertyName;
  protected $sourceTypes = [
    'string'
  ];
  protected DataMapFactory|null $dataMapFactory = null;
  protected FileRepository|null $fileRepository = null;

  public function __construct(DataMapFactory $dataMapFactory, FileRepository $fileRepository)
  {
      $this->dataMapFactory = $dataMapFactory;
      $this->fileRepository = $fileRepository;
  }

  /**
   * @param AbstractEntity $object
   * @param string $propertyName
   * @return void
   */
  public function setParentObjectAndProperty(AbstractEntity $object, string $propertyName): void
  {
    $this->parentObject = $object;
    $this->propertyName = $propertyName;
  }

  /**
   * Converts an input remote ID to a FileReference pointing to the
   * File object which has the remote ID.
   *
   * @param mixed $source
   * @param string $targetType
   * @param array $convertedChildProperties
   * @param PropertyMappingConfigurationInterface|null $configuration
   * @return object|null
   * @throws Exception
   * @throws InvalidSourceException
   * @throws TargetNotFoundException
   */
  public function convertFrom(
    $source,
    string $targetType,
    array $convertedChildProperties = [],
    PropertyMappingConfigurationInterface $configuration = null
  ): ?object {
    if (!isset($this->parentObject)) {
      return null;
    }
    $dataMap = $this->dataMapFactory->buildDataMap(get_class($this->parentObject));

    $systemLanguageUid = (int)$this->parentObject->_getProperty('_languageUid');
    $fieldName = $dataMap->getColumnMap($this->propertyName)->getColumnName(); // GeneralUtility::camelCaseToLowerCaseUnderscored($this->propertyName);

    // Lookup no. 1: try to find a sys_file_reference pointing to the sys_file with remote ID=$source
    // and matching relation values to $this->parentObject and $this->propertyName. We do this because
    // there is no Repository which we could use to load an Extbase file reference base on criteria.
    // So instead we probe the DB and if a match is found, we know the existing property value is the
    // exact same relation we were asked to convert - and we return the current property value.
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
      ->getConnectionForTable('sys_file')
      ->createQueryBuilder();
    $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
    $constraints = [
      $queryBuilder->expr()->eq('f.remote_id', $queryBuilder->quote($source, \PDO::PARAM_STR)),
      $queryBuilder->expr()->eq('r.tablenames', $queryBuilder->quote($dataMap->getTableName(), \PDO::PARAM_STR)),
      $queryBuilder->expr()->eq('r.fieldname', $queryBuilder->quote($fieldName, \PDO::PARAM_STR)),
      $queryBuilder->expr()->eq('r.uid_foreign', $queryBuilder->quote((int)$this->parentObject->getUid(), \PDO::PARAM_INT)),
      $queryBuilder->expr()->eq('r.sys_language_uid', $queryBuilder->quote((int)$systemLanguageUid, \PDO::PARAM_INT)),
    ];
    $query = $queryBuilder->select('r.*')
      ->from('sys_file', 'f')
      ->join('f', 'sys_file_reference', 'r', 'r.uid_local = f.uid')
      ->where(...$constraints)
      ->setMaxResults(1);
    $references = $query
        ->executeQuery()
        ->fetchAllAssociative();
    if (isset($references[0]['uid'])) {
      return $this->fetchObjectFromPersistence((int)$references[0]['uid'], $targetType);
    }

    // Lookup no. 2: try to find a sys_file with remote ID=$source and use it as target for a new
    // file relation. If the original file cannot be found this way the relation is considered
    // invalid or impossible to resolve - and an exception is thrown, causing the importing to be
    // resumed on next run which should then have imported the target file so we can point to it.
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
      ->getConnectionForTable('sys_file')
      ->createQueryBuilder();
    $original = $queryBuilder
      ->select('f.uid')
      ->from('sys_file', 'f')
      ->where($queryBuilder->expr()->eq('f.remote_id', $queryBuilder->quote($source)))
      ->setMaxResults(1)
      ->executeQuery()
      ->fetchAllAssociative();
    if (!isset($original[0]['uid'])) {
      $parentObjectId = method_exists($this->parentObject, 'getRemoteId') ? $this->parentObject->getRemoteId() : $this->parentObject->getUid();
      throw new DeferralException(
        'Unable to map ' . $this->propertyName . ' on ' . get_class($this->parentObject) . ':' . $parentObjectId .
        ' - Asset ' . $source . ' does not appear to exist (yet).',
        1527167261
      );
    }

    // File reference object needs to be created with the exact composition of this array. Not
    // passing either one of these parameters causes an invalid file reference to be written.
    $referenceProperties = [
      //'pid' => $this->parentObject->getPid(),
      'tablenames' => $dataMap->getTableName(),
      'fieldname' => $fieldName,
      'uid_local' => $original[0]['uid'],
      'uid_foreign' => $this->parentObject->getUid(),
      $GLOBALS['TCA']['sys_file_reference']['ctrl']['languageField'] => $systemLanguageUid,
    ];

    if ($GLOBALS['TCA']['sys_file_reference']['ctrl']['crdate']) {
        $referenceProperties[$GLOBALS['TCA']['sys_file_reference']['ctrl']['crdate']] = time();
    }

    if ($GLOBALS['TCA']['sys_file_reference']['ctrl']['tstamp']) {
        $referenceProperties[$GLOBALS['TCA']['sys_file_reference']['ctrl']['tstamp']] = time();
    }

    if ($systemLanguageUid > 0) {
      // Value of 'uid_local' pointed to the translated file
      $translatedFile = $this->determineDataUid(
        'sys_file',
        (int)($original[0]['uid'] ?? 0),
        $systemLanguageUid
      );

      $originalFile = $translatedFile !== (int)($original[0]['uid']) ? (int)($original[0]['uid']) : $translatedFile;

      // Point to the possible translated file
      if ($originalFile !== $translatedFile) {
        $referenceProperties['uid_local'] = $translatedFile;
      }

      // The value of uid foreign should point to the uid of the translation
      $uidForeign = $this->determineDataUid(
        $dataMap->getTableName(),
        $this->parentObject->getUid(),
        $systemLanguageUid
      );
      $originalData = $this->parentObject->getUid();

      if ($uidForeign > 0) {
          $originalData = $uidForeign !== $this->parentObject->getUid() ? $this->parentObject->getUid() : $uidForeign;
          // Point to the possible translated data set
          if ($uidForeign !== $originalData) {
              $referenceProperties['uid_foreign'] = $uidForeign;
          }
      }

      /*
       * Add translation information
       * The field 'uid_local' should point to a possible translated file id
       * The field 'uid_foreign' should point to the translated data
       * The field 'transOrigPointerField' must point to the original reference entry
       */
      $originReference = $this->findReferenceOrigin(
        $dataMap->getTableName(),
        $fieldName,
        $originalFile,
        $originalData
      );

      if ($originReference !== false) {
        $referenceProperties[$GLOBALS['TCA']['sys_file_reference']['ctrl']['transOrigPointerField']] = $originReference['uid'];
      }
    }

    $connection = GeneralUtility::makeInstance(ConnectionPool::class)
      ->getConnectionForTable('sys_file_reference');
    $connection->insert('sys_file_reference', $referenceProperties);
    $referenceProperties['uid'] = $connection->lastInsertId('sys_file_reference');
    return $this->fetchObjectFromPersistence((int)$referenceProperties['uid'], $targetType);
  }

  /**
   * Determine the correct data uid
   *
   * @param string $tableName
   * @param int $uid
   * @param int $languageUid
   * @return int
   */
  protected function determineDataUid(string $tableName, int $uid, int $languageUid = 0): int
  {
    if ($uid === 0) {
      return 0;
    }
    $columnTranslation = $GLOBALS['TCA'][$tableName]['ctrl']['languageField'] ?? null;
    $columnTranslationParent = $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'] ?? null;
    if (empty($columnTranslation) || empty($columnTranslationParent)) {
      return $uid;
    }
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
      ->getConnectionForTable($tableName)
      ->createQueryBuilder();
    $translation = $queryBuilder
      ->select('uid')
      ->from($tableName)
      ->where(
        $queryBuilder->expr()->eq($columnTranslation, $queryBuilder->quote($languageUid, \PDO::PARAM_INT)),
        $queryBuilder->expr()->eq($columnTranslationParent, $queryBuilder->quote($uid, \PDO::PARAM_INT)),
      )
      ->setMaxResults(1)
      ->executeQuery()
      ->fetchAllAssociative();
    if (empty($translation)) {
        return $uid;
    } else {
        return (int)$translation[0]['uid'];
    }
  }

  protected function findReferenceOrigin(
    string $tableName,
    string $fieldName,
    int $fileUid,
    int $referenceUid
  ): array|false {
      $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
        ->getConnectionForTable('sys_file_reference')
        ->createQueryBuilder();
      return $queryBuilder
        ->select('f.uid')
        ->from('sys_file_reference', 'f')
        ->where(
          $queryBuilder->expr()->eq('tablenames', $queryBuilder->quote($tableName, \PDO::PARAM_STR)),
          $queryBuilder->expr()->eq('fieldname', $queryBuilder->quote($fieldName, \PDO::PARAM_STR)),
          $queryBuilder->expr()->eq('uid_local', $queryBuilder->quote($fileUid, \PDO::PARAM_INT)),
          $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->quote($referenceUid, \PDO::PARAM_INT)),
        )
        ->executeQuery()
        ->fetchAssociative();
  }

  /**
   * @return RepositoryInterface
   */
  protected function getRepository(): RepositoryInterface
  {
    return $this->fileRepository;
  }
}
