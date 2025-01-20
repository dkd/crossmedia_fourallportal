<?php

namespace Crossmedia\Fourallportal\Mapping;

use Crossmedia\Fourallportal\Domain\Model\DimensionMapping;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Fourallportal\Service\LoggingService;
use Crossmedia\Fourallportal\TypeConverter\PimBasedTypeConverterInterface;
use Crossmedia\Fourallportal\ValueReader\ResponseDataFieldValueReader;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\RepositoryInterface;
use TYPO3\CMS\Extbase\Property\Exception\InvalidSourceException;
use TYPO3\CMS\Extbase\Property\Exception\TypeConverterException;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;
use TYPO3\CMS\Extbase\Property\TypeConverter\PersistentObjectConverter;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchMethodException;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchMethodParameterException;
use TYPO3\CMS\Extbase\Reflection\ClassSchema\Exception\NoSuchPropertyException;
use TYPO3\CMS\Extbase\Reflection\Exception\PropertyNotAccessibleException;
use TYPO3\CMS\Extbase\Reflection\Exception\UnknownClassException;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Validation\Error;
use TYPO3\CMS\Extbase\Reflection\ReflectionService;

abstract class AbstractMapping implements MappingInterface
{
  protected string $repositoryClassName;
  protected ?LoggingService $loggingService = null;
  protected ?PersistenceManager $persistenceManager = null;
  protected ?AccessiblePropertyMapper $accessiblePropertyMapper = null;
  protected ?StorageRepository $storageRepository = null;
  protected ?Session $session = null;
  protected ?ConnectionPool $connectionPool = null;

  public function __construct(
    ?LoggingService $loggingService = null,
    ?PersistenceManager $persistenceManager = null,
    ?AccessiblePropertyMapper $accessiblePropertyMapper = null,
    ?StorageRepository $storageRepository = null,
    ?Session $session = null,
    ?ConnectionPool $connectionPool = null
  ) {
    $this->loggingService = $loggingService ?? GeneralUtility::makeInstance(LoggingService::class);
    $this->persistenceManager = $persistenceManager ?? GeneralUtility::makeInstance(PersistenceManager::class);
    $this->accessiblePropertyMapper = $accessiblePropertyMapper ?? GeneralUtility::makeInstance(AccessiblePropertyMapper::class);
    $this->storageRepository = $storageRepository ?? GeneralUtility::makeInstance(StorageRepository::class);
    $this->session = $session ?? GeneralUtility::makeInstance(Session::class);
    $this->connectionPool = $connectionPool ?? GeneralUtility::makeInstance(ConnectionPool::class);
  }

  /**
   * @param array $data
   * @param Event $event
   * @return bool
   * @throws \Doctrine\DBAL\Exception
   * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
   */
  public function import(array $data, Event $event): bool
  {
    if ($this->sanityCheckAndAutoRepair($data, $event)) {
      // A failed sanity check may mean DB contents have been repaired.
      // To be safe, we defer the event once so the next time it gets
      // processed the session will be clean.
      return true;
    }

    $objectId = $event->getObjectId();
    $repository = $this->getObjectRepository();
    $query = $repository->createQuery();
    $query->getQuerySettings()->setRespectStoragePage(false);
    $query->getQuerySettings()->setRespectSysLanguage(false);
    $query->getQuerySettings()->setIgnoreEnableFields(true);
    $query->getQuerySettings()->setLanguageUid(0);
    $query->matching($query->equals('remoteId', $objectId));
    $object = $query->execute()->current();
    $deferAfterProcessing = false;

    switch ($event->getEventType()) {
      case 'delete':
        if (!$object) {
          // push back event.
          break;
        }
        $this->removeObjectFromRepository($object);
        $this->loggingService->logObjectActivity(
          $objectId,
          sprintf(
            'Object %s was deleted by event %s:%d',
            $event->getObjectId(),
            $event->getModule()->getModuleName(),
            $event->getEventId()
          ),
          'tstamp'
        );
        unset($object);
        break;
      case 'update':
      case 'create':
        $deferAfterProcessing = $this->importObjectWithDimensionMappings($data, $object, $event);
        $this->loggingService->logObjectActivity(
          $objectId,
          sprintf(
            'Object %s was updated by event %s:%d',
            $event->getObjectId(),
            $event->getModule()->getModuleName(),
            $event->getEventId()
          ),
          'tstamp'
        );
        break;
      default:
        throw new RuntimeException('Unknown event type: ' . $event->getEventType(), 4961585388);
    }

    if (isset($object)) {
      $this->processRelationships($object, $data, $event);
    }

    if ($deferAfterProcessing) {
      $this->loggingService->logEventActivity($event, 'Event deferred');
    }

    return $deferAfterProcessing;
  }

  /**
   * Sanity check (local) data before allowing $event to be
   * processed. Returning TRUE defers the event.
   *
   * Happens before the Extbase persistence is engaged.
   *
   * Your sanity check should NOT use Extbase persistence
   * as this will cause the session to hold on to the objects
   * you load.
   *
   * If your sanity check was unable to repair whichever
   * problems it detected, make sure you log this via the
   * provided object logger!
   *
   * @param array $data
   * @param Event $event
   * @return bool
   * @throws \Doctrine\DBAL\Exception
   * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
   */
  protected function sanityCheckAndAutoRepair(array $data, Event $event): bool
  {
    $objectId = $event->getObjectId();
    $tableName = $this->getTableName();
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
    $queryBuilder->getRestrictions()->removeAll();
    $pimRecords = $queryBuilder->select('*')
      ->from($tableName)
      ->where($queryBuilder->expr()->eq('remote_id', $queryBuilder->createNamedParameter($objectId)))
      ->orderBy('sys_language_uid', 'ASC')
      ->executeQuery()
      ->fetchFirstColumn();
    $defer = false;

    if (empty($pimRecords)) {
      return $defer;
    }

    // Native check 1: Verify that all records from table associated with object have the same "pid",
    // have the right "l10n_parent" for translated records, and does not contain more than one record
    // for each unique language. Attempt to repair by deleting records (which will then be recreated).
    // Log anything that might be wrong/fixed.
    $languageUids = array_column($pimRecords, 'sys_language_uid');
    $pids = array_column($pimRecords, 'pid');
    $pid = reset($pids);
    if (count(array_unique($pids)) !== 1) {
      // One or more records do not have the right pid. Delete those that differ if their language UID is non-zero.
      $encountered = [];
      foreach ($pimRecords as $index => &$record) {
        if (!in_array($record['pid'], $encountered) && $record['sys_language_uid'] > 0) {
          $message = sprintf('Record %s from table %s has the wrong pid %d, setting it to %d', $record['uid'], $tableName, $record['pid'], $pid);
          $this->loggingService->logEventActivity($event, $message);
          $this->loggingService->logObjectActivity($data['result'][0]['id'], $message, 'pid');
          $record['pid'] = $pid;
          $this->updateRecord($tableName, $record);
          $defer = true;
          unset($pimRecords[$index]);
        } else {
          $encountered[] = $record['pid'];
        }
      }
    }
    if (count(array_unique($languageUids)) !== count($languageUids)) {
      // One or more records share the same language UID. We may have to remove some records.
      $encountered = [];
      foreach ($pimRecords as $index => $record) {
        if ($record['deleted']) {
          $message = sprintf('Record %s from table %s is soft-deleted; hard-delete it', $record['uid'], $tableName);
          $this->loggingService->logEventActivity($event, $message);
          $this->loggingService->logObjectActivity($data['result'][0]['id'], $message, 'uid');
          $this->hardDeleteRecord($tableName, $record);
          continue;
        }
        if (in_array($record['sys_language_uid'], $encountered)) {
          $message = sprintf('Record %s from table %s is a language duplicate', $record['uid'], $tableName);
          $this->loggingService->logEventActivity($event, $message);
          $this->loggingService->logObjectActivity($data['result'][0]['id'], $message, 'uid');
          $this->hardDeleteRecord($tableName, $record);
          $defer = true;
          unset($pimRecords[$index]);
        } else {
          $encountered[] = $record['sys_language_uid'];
        }
      }
    }

    return $defer;
  }

  /**
   * @param string $table
   * @param array $record
   * @return void
   */
  protected function updateRecord(string $table, array $record): void
  {
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    $queryBuilder->getRestrictions()->removeAll();
    $query = $queryBuilder->update($table)->where($queryBuilder->expr()->eq('uid', $record['uid']));
    foreach ($record as $key => $value) {
      $query->set($key, $value);
    }
    $query->executeQuery();
    $message = sprintf('Record %s from table %s was updated', $record['uid'], $table);
    $this->loggingService->logObjectActivity($record['remote_id'] ?? 'unknown', $message, 'uid');
  }

  /**
   * @param string $table
   * @param array $record
   * @return void
   */
  protected function hardDeleteRecord(string $table, array $record): void
  {
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    $queryBuilder->getRestrictions()->removeAll();
    $queryBuilder->delete($table)->where($queryBuilder->expr()->eq('uid', $record['uid']));
    $queryBuilder->executeQuery();
    $message = sprintf('Record %s was deleted from table %s', $record['uid'], $table);
    $this->loggingService->logObjectActivity($record['remote_id'] ?? 'unknown', $message, 'uid');
  }

  protected function removeObject(DomainObjectInterface $object): void
  {
    $this->persistenceManager->remove($object);
  }

  protected function removeObjectFromRepository(DomainObjectInterface $object): void
  {
    $this->getObjectRepository()->remove($object);
  }

  /**
   * @param array $data
   * @param AbstractEntity $object
   * @param Module $module
   * @param DimensionMapping|null $dimensionMapping
   * @return bool
   * @throws InvalidSourceException
   * @throws ReflectionException
   * @throws TypeConverterException
   */
  protected function mapPropertiesFromDataToObject(array $data, AbstractEntity|File $object, Module $module, DimensionMapping $dimensionMapping = null): bool
  {
    if (!$data['result']) {
      return true;
    }
    $map = MappingRegister::resolvePropertyMapForMapper(static::class);
    $properties = $data['result'][0]['properties'];
    $responseValueReader = new ResponseDataFieldValueReader();
    $properties = $this->addMissingNullProperties($properties, $module);
    $mappingProblemsOccurred = false;
    $objectId = $object->getUid();
    if (method_exists($object, 'getRemoteId')) {
      $objectId = $object->getRemoteId();
    }
    foreach ($properties as $importedName => $propertyValue) {
      if (($map[$importedName] ?? null) === false) {
        continue;
      }
      try {
        $propertyValue = $responseValueReader->readResponseDataField($data['result'][0], $importedName, $dimensionMapping);
        $customSetter = MappingRegister::resolvePropertyValueSetter(static::class, $importedName);
        if ($customSetter) {
          $customSetter->setValueOnObject($propertyValue, $importedName, $data, $object, $module, $this, $dimensionMapping);
        } else {
          $targetPropertyName = $map[$importedName] ?? GeneralUtility::underscoredToLowerCamelCase($importedName);
          $propertyMappingProblemsOccurred = $this->mapPropertyValueToObject($targetPropertyName, $propertyValue, $object);
          $mappingProblemsOccurred = $mappingProblemsOccurred ?: $propertyMappingProblemsOccurred;
        }
      } catch (PropertyNotAccessibleException $error) {
        $message = 'Error mapping ' . $module->getModuleName() . ':' . $objectId . ':' . $importedName . ' - ' . $error->getMessage();
        $this->loggingService->logObjectActivity($data['result'][0]['id'], $message, 3 /*GeneralUtility::SYSLOG_SEVERITY_WARNING*/);
      } catch (DeferralException $error) {
        $message = 'Error mapping ' . $module->getModuleName() . ':' . $objectId . ':' . $importedName . ' - ' . $error->getMessage();
        $this->loggingService->logObjectActivity($data['result'][0]['id'], $message, 3 /*GeneralUtility::SYSLOG_SEVERITY_WARNING*/);
      }
    }
    return $mappingProblemsOccurred;
  }

  /**
   * @param string $propertyName
   * @param mixed $propertyValue
   * @param AbstractEntity $object
   * @return bool
   * @throws PropertyNotAccessibleException
   * @throws ReflectionException
   * @throws InvalidSourceException
   * @throws TypeConverterException
   */
  protected function mapPropertyValueToObject(string $propertyName, mixed $propertyValue, AbstractEntity|File $object): bool
  {
    if (!property_exists(get_class($object), $propertyName)) {
      return false;
    }

    /*
    $currentPropertyValue = ObjectAccess::getProperty($object, $propertyName);
    // We need to check if the current value is an instance of the special FileReference proxy, which if it is, needs
    // to be explicitly removed from the repository so the mapping can create fresh instances.
    if ($currentPropertyValue instanceof FileReference) {
        // We unset, but do not return, so that:
        // 1) if the value is null, the property gets nulled and we return just below here
        // 2) if it is not, a new reference will be created and the value overridden in the end of the function.
        $this->removeObject($currentPropertyValue);
        $this->persist();
    }
    */

    $objectId = 'unknown';
    if (method_exists($object, 'getRemoteId')) {
      $objectId = $object->getRemoteId();
    }

    $array = (new ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getParameters();
    if ($propertyValue === null && reset($array)->allowsNull()) {
      ObjectAccess::setProperty($object, $propertyName, null);
      return false;
    }
    $configuration = new PropertyMappingConfiguration();
    $mappingProblemsOccurred = false;

    $configuration->allowAllProperties();
    $configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_CREATION_ALLOWED, true);
    $configuration->setTypeConverterOption(PersistentObjectConverter::class, PersistentObjectConverter::CONFIGURATION_MODIFICATION_ALLOWED, true);
    $configuration->setTypeConverterOption(DateTimeConverter::class, DateTimeConverter::CONFIGURATION_DATE_FORMAT, 'Y#m#d\\TH#i#s+');

    $propertyMapper = $this->getAccessiblePropertyMapper();
    $targetType = $this->determineDataTypeForProperty($propertyName, $object);
    $array1 = (new ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getParameters();
    if (strpos($targetType, '<')) {
      $childType = substr($targetType, strpos($targetType, '<') + 1, -1);
      $childType = trim($childType, '\\');
      $objectStorage = new ObjectStorage();

      if (!empty($propertyValue)) {
        foreach ((array)$propertyValue as $identifier) {
          if (!$identifier) {
            continue;
          }
          $typeConverter = $propertyMapper->findTypeConverter($identifier, $childType, $configuration);
          if ($typeConverter instanceof PimBasedTypeConverterInterface) {
            $typeConverter->setParentObjectAndProperty($object, $propertyName);
          }

          try {
            $child = $typeConverter->convertFrom($identifier, $childType, [], $configuration);
          } catch (DeferralException $error) {
            $this->loggingService->logObjectActivity($objectId, $error->getMessage(), 3 /*GeneralUtility::SYSLOG_SEVERITY_WARNING*/);
            $mappingProblemsOccurred = true;
            continue;
          }

          $refObject = new ReflectionClass($object);
          $languageIdProperty = $refObject->getProperty('_languageUid');
          // set driver property to public
          /** @noinspection PhpExpressionResultUnusedInspection */
          $languageIdProperty->setAccessible(true);
          $languageUid = $languageIdProperty->getValue($object);

          if ($child instanceof Error) {
            // For whatever reason, property validators will return a validation error rather than throw an exception.
            // We therefore need to check this, log the problem, and skip the property.
            $message = 'Mapping error when mapping property ' . $propertyName . ' on ' . get_class($object) . ':' . $objectId .
              ' in language UID ' . $languageUid . ': ' . $child->getMessage();
            $this->loggingService->logObjectActivity($objectId, $message, 3 /*GeneralUtility::SYSLOG_SEVERITY_WARNING*/);
            $child = null;
          }

          if (!$child) {
            $message = 'Child of type ' . $childType . ' identified by ' . $identifier . ' not found when mapping property ' .
              $propertyName . ' on ' . get_class($object) . ':' . $objectId . ' in language UID ' . $languageUid;
            $this->loggingService->logObjectActivity($objectId, $message, 3 /*GeneralUtility::SYSLOG_SEVERITY_WARNING*/);
            $mappingProblemsOccurred = true;
            continue;
          }

          $objectStorage->attach($child);
        }
      }

      $propertyValue = $objectStorage;

    } elseif ($propertyValue !== null) {
      $sourceType = $propertyMapper->determineSourceType($propertyValue);
      $targetType = trim($targetType, '\\?');
      if ($targetType !== $sourceType) {
        if ($targetType === 'string' && $sourceType === 'array') {
          $propertyValue = json_encode($propertyValue, JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG);
        } else {
          $typeConverter = $propertyMapper->findTypeConverter($propertyValue, $targetType, $configuration);
          if ($typeConverter instanceof PimBasedTypeConverterInterface) {
            $typeConverter->setParentObjectAndProperty($object, $propertyName);
          }
          $propertyValue = $typeConverter->convertFrom($propertyValue, $targetType, [], $configuration);
        }

        if ($propertyValue instanceof Error) {
          // For whatever reason, property validators will return a validation error rather than throw an exception.
          // We therefore need to check this, log the problem, and skip the property.
          $message = 'Mapping error when mapping property ' . $propertyName . ' on ' . get_class($object) . ':' . $objectId . ': ' . $propertyValue->getMessage();
          $this->loggingService->logObjectActivity($objectId, $message, 3 /*GeneralUtility::SYSLOG_SEVERITY_WARNING*/);
          $propertyValue = null;
        }

        // Sanity filter: do not attempt to set Entity with setter if an instance is required but the value is null.
        if ((new ReflectionMethod(get_class($object), 'set' . ucfirst($propertyName)))->getNumberOfRequiredParameters() === 1) {
          if (is_null($propertyValue) && is_a($targetType, AbstractEntity::class, true)) {
            return false;
          }
        }
      }
    } elseif ($propertyValue === null && !reset($array1)->allowsNull()) {
      $message = sprintf(
        'Property "%s" on object "%s->%s" does not allow NULL as value, but NULL was resolved. Please verify PIM response data consistency!',
        $propertyName,
        get_class($object),
        method_exists($object, 'getRemoteId') ? $object->getRemoteId() : $object->getUid()
      );
      $this->loggingService->logObjectActivity($objectId, $message, 4 /*GeneralUtility::SYSLOG_SEVERITY_FATAL*/);
      return false;
    }

    $setOnObject = $object;
    $lastPropertyName = $propertyName;
    if (strpos($propertyName, '.') !== false) {
      $propertyPath = explode('.', $propertyName);
      $lastPropertyName = array_pop($propertyPath);
      foreach ($propertyPath as $currentPropertyName) {
        $setOnObject = ObjectAccess::getProperty($setOnObject, $currentPropertyName);
      }
    }

    ObjectAccess::setProperty($setOnObject, $lastPropertyName, $propertyValue);

    return $mappingProblemsOccurred;
  }

  /**
   * @param $propertyName
   * @param $object
   * @return string|false
   * @throws NoSuchMethodException
   * @throws NoSuchMethodParameterException
   * @throws NoSuchPropertyException
   * @throws UnknownClassException
   */
  protected function determineDataTypeForProperty($propertyName, $object): bool|string
  {
    if (property_exists(get_class($object), $propertyName)) {
      $reflectionService = GeneralUtility::makeInstance(ReflectionService::class);
      $classSchema = $reflectionService->getClassSchema($object);
      $property = $classSchema->getProperty($propertyName);
      if ($property->getPrimaryType()->isCollection()) {
        $types = array_map(
          function (\Symfony\Component\PropertyInfo\Type $type) {
            return $type->getClassName();
          },
          $property->getPrimaryType()->getCollectionValueTypes()
        );
        return $property->getPrimaryType()->getClassName() . '<' .  implode('|', $types) . '>';
      }
      return $property->getPrimaryType()->getBuiltinType();
    }

    throw new RuntimeException('Type of property ' . $propertyName . ' on ' . get_class($object) . ' could not be determined', 8035246555);
  }

  /**
   * @param $object
   * @param array $data
   * @param Event $event
   */
  protected function processRelationships($object, array $data, Event $event)
  {

  }

  /**
   * @return string
   */
  public function getEntityClassName(): string
  {
    return substr(str_replace('\\Domain\\Repository\\', '\\Domain\\Model\\', $this->repositoryClassName), 0, -10);
  }

  /**
   * @return AccessiblePropertyMapper
   */
  protected function getAccessiblePropertyMapper(): AccessiblePropertyMapper
  {
    return $this->accessiblePropertyMapper;
  }

  /**
   * @return RepositoryInterface
   */
  public function getObjectRepository(): RepositoryInterface
  {
    return GeneralUtility::makeInstance($this->repositoryClassName);
  }

  /**
   * @param ApiClient $client
   * @param Module $module
   * @param array $status
   * @return array
   * @throws ApiException
   */
  public function check(ApiClient $client, Module $module, array $status): array
  {
    $messages = [];
    // Verify the local mapping configuration exists and points to correct properties
    $entityClass = $this->getEntityClassName();
    $map = MappingRegister::resolvePropertyMapForMapper(get_class($this));
    $messages['property_checks'] = '<h4>
                Property mapping checks
            </h4>';
    if (empty($map)) {
      $messages[] = sprintf(
        '<p class="text-warning">This connector has no mapping information - fields will be mapped 1:1 to properties on %s</p>',
        $entityClass
      );
    }

    $connectorConfiguration = $module->getConnectorConfiguration();
    $moduleConfiguration = $module->getModuleConfiguration();
    $allFields = $moduleConfiguration['field_conf'] + $moduleConfiguration['relation_conf'];
    $loadedFields = array_keys($connectorConfiguration['fieldsToLoad']);

    $fieldTypes = array_column($allFields, 'type', 'name');
    $messages[] = '<table class="table table-bordered table-striped">';
    $messages[] = '<thead>';
    $messages[] = '<tr>';
    $messages[] = '<td>Source property</td>';
    $messages[] = '<td>Destination</td>';
    $messages[] = '<td>Loaded?</td>';
    $messages[] = '<td>Type</td>';
    $messages[] = '</tr>';
    $messages[] = '</thead>';

    foreach ($allFields as $sourcePropertyName => $fieldConfiguration) {
      $destinationPropertyName = GeneralUtility::underscoredToLowerCamelCase($sourcePropertyName);
      $class = '';

      if (isset($map[$sourcePropertyName])) {
        if (!$map[$sourcePropertyName]) {
          $destinationPropertyName = 'ignored';
          $class = 'text-warning';
        } else {
          $destinationPropertyName = $map[$sourcePropertyName];
          $class = 'text-info';
        }
      }

      $propertyExists = property_exists($entityClass, $destinationPropertyName);
      $setterMethod = 'set' . ucfirst($destinationPropertyName);
      $isCustomSetter = class_exists($destinationPropertyName) && is_a($destinationPropertyName, ValueSetterInterface::class, true);

      if ($propertyExists) {
        $class = 'text-success';
      } elseif (!$isCustomSetter) {
        $class = 'text-warning';
        $destinationPropertyName = sprintf(
          'Field <strong>%s</strong> needs to be mapped or ignored.',
          $destinationPropertyName
        );
      } elseif (method_exists($entityClass, $setterMethod)) {
        $destinationPropertyName .= ' with setter ' . $setterMethod;
      } elseif ($isCustomSetter) {
        $destinationPropertyName = 'Custom value setter: ' . $destinationPropertyName;
        $class = 'text-success';
      } else {
        $class = 'text-danger';
      }

      $loaded = in_array($sourcePropertyName, $loadedFields, true) ? '<span>&#10003;</span>' : '';
      $type = isset($fieldTypes[$sourcePropertyName]) ? $fieldTypes[$sourcePropertyName] : 'undefined';

      $messages[] = '<tr class="' . $class . '">';
      $messages[] = '<td>' . $sourcePropertyName . '</td>';
      $messages[] = '<td>' . $destinationPropertyName . '</td>';
      $messages[] = '<td>' . $loaded . '</td>';
      $messages[] = '<td>' . $type . '</td>';
      $messages[] = '</tr>';
    }
    $messages[] = '</table>';

    $status['description'] .= implode(chr(10), $messages);
    return $status;
  }

  /**
   * @param $properties
   * @param Module $module
   * @return mixed
   * @throws ApiException
   */
  protected function addMissingNullProperties($properties, Module $module): mixed
  {
    $moduleConfiguration = $module->getModuleConfiguration();
    foreach ($moduleConfiguration['relation_conf'] as $field) {
      if (!isset($properties[$field['name']])) {
        $properties[$field['name']]['value'] = null;
      }
    }
    foreach ($moduleConfiguration['field_conf'] as $field) {
      if (!isset($properties[$field['name']])) {
        $value = '';
        if (isset($field['defaultValue'])) {
          $value = $field['defaultValue'];
        } else {
          switch ($field['type']) {
            case 'CEVarchar':
              $value = '';
              break;
            case 'MAMDate':
            case 'CEDate':
              $value = null;
              break;
            case 'MAMBoolean';
            case 'CEBoolean':
              $value = false;
              break;
            case 'CEDouble':
              $value = 0.0;
              break;
            case 'CETimestamp':
            case 'CEInteger':
            case 'CELong':
            case 'MAMNumber':
            case 'XMPNumber':
              $value = 0;
              break;
            case 'MAMList':
            case 'CEVarcharList':
            case 'FIELD_LINK':
            case 'CEExternalIdList':
            case 'CEIdList':
            case 'MANY_TO_MANY':
            case 'ONE_TO_MANY':
            case 'MANY_TO_ONE':
              $value = [];
              break;
            case 'CEId':
            case 'CEExternalId':
            case 'ONE_TO_ONE':
              $value = null;
              break;
            default:
              break;
          }
        }
        $properties[$field['name']]['value'] = $value;
      }
    }

    return $properties;
  }

  /**
   * @param Event $event
   * @param int $systemLanguage
   * @param int $languageParentUid
   * @param null $existingRow
   * @return mixed
   * @throws Exception
   */
  protected function createObject(Event $event, int $systemLanguage = 0, int $languageParentUid = 0, $existingRow = null): mixed
  {
    if ($systemLanguage > 0 && $languageParentUid === 0) {
      throw new Exception(
        sprintf(
          'Will not create record for "%s:%s" in language "%d" since no translation parent was provided.',
          $event->getModule()->getModuleName(),
          $event->getObjectId(),
          $systemLanguage
        ), 3193154734
      );
    }

    $recordUid = (int)(($existingRow['l10n_parent'] ?? false) ?: ($existingRow['uid'] ?? false) ?: 0);

    if ($recordUid === 0) {
      $newRecordValues = [
        'pid' => $event->getModule()->getStoragePid(),
        'sys_language_uid' => $systemLanguage,
        'l10n_parent' => $languageParentUid,
        'remote_id' => $event->getObjectId(),
        'crdate' => time(),
      ];
      $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->getTableName());
      $numberOfInsertedRows = $queryBuilder
        ->insert($this->getTableName())
        ->values($newRecordValues)
        ->executeStatement();
      $recordUid = $queryBuilder->getConnection()->lastInsertId();
    }

    $this->persist();

    $entityClassName = $event->getModule()->getMapper()->getEntityClassName();
    if ($this->session->hasIdentifier($recordUid, $entityClassName)) {
      $recordedObject = $this->session->getObjectByIdentifier($recordUid, $entityClassName);
      $this->session->unregisterObject($recordedObject);
      $this->session->unregisterReconstitutedEntity($recordedObject);
    }

    if ($languageParentUid > 0 && $this->session->hasIdentifier($languageParentUid, $entityClassName)) {
      $recordedObject = $this->session->getObjectByIdentifier($languageParentUid, $entityClassName);
      $this->session->unregisterObject($recordedObject);
      $this->session->unregisterReconstitutedEntity($recordedObject);
    }

    $query = $this->getObjectRepository()->createQuery();
    $query->getQuerySettings()
      ->setRespectSysLanguage(false)
      ->setIncludeDeleted(false)
      ->setIgnoreEnableFields(true)
      ->setRespectStoragePage(false)
      ->setLanguageUid($systemLanguage);
    //->setLanguageMode('strict')
    //->setLanguageOverlayMode('hideNonTranslated');

    $createdObject = $query->matching($query->equals('remote_id', $event->getObjectId()))->execute()->getFirst();
    if (!$createdObject) {
      throw new Exception(
        sprintf(
          'Unable to create object "%s:%s" in language "%d". Expected record UID: %s',
          $event->getModule()->getModuleName(),
          $event->getObjectId(),
          $systemLanguage,
          $recordUid
        ), 4866018335
      );
    }

    if ($systemLanguage) {
      $createdObject->_setProperty('_localizedUid', (int)$existingRow['uid'] ?? $recordUid);
      $createdObject->_setProperty('_languageUid', $systemLanguage);
      $createdObject->setRemoteId($event->getObjectId());
    }
    return $createdObject;
  }

  /**
   * Persists all objects pending for ORM
   * @return void
   */
  protected function persist(): void
  {
    $this->persistenceManager->persistAll();
  }

  /**
   * @return string
   * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
   */
  public function getTableName(): string
  {
    $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
    return $dataMapper->getDataMap($this->getEntityClassName())->getTableName();
  }

  /**
   * @param array $data
   * @param $object
   * @param Event $event
   * @return bool
   * @throws Exception
   */
  protected function importObjectWithDimensionMappings(array $data, $object, Event $event): bool
  {
    $request = $this->request ?? $GLOBALS['TYPO3_REQUEST'];
    $frontendTypoScript = $request->getAttribute('frontend.typoscript');
    if ($frontendTypoScript instanceof FrontendTypoScript) {
      $GLOBALS['TSFE'] = $frontendTypoScript;
      $GLOBALS['TSFE']['sys_page'] = new PageRepository();
      $GLOBALS['TSFE']['sys_page']['sys_language_uid'] = 0;
      $GLOBALS['TSFE']['sys_language_content'] = 0;
      $GLOBALS['TSFE']['config']['sys_language_uid'] = 0;
    }
    /**  @see .build/vendor/typo3/cms-core/Documentation/Changelog/8.0/Breaking-72424-RemovedDeprecatedTypoScriptFrontendControllerOptionsAndMethods.rst */
//    $GLOBALS['TSFE'] = new TypoScriptFrontendController($GLOBALS['TYPO3_CONF_VARS'], $event->getModule()->getStoragePid(), 0);
//    $GLOBALS['TSFE']->sys_page = new PageRepository();
//    $GLOBALS['TSFE']->sys_page->sys_language_uid = 0;
//    $GLOBALS['TSFE']->getPageAndRootline();
//    $GLOBALS['TSFE']->sys_language_content = 0;
//    $GLOBALS['TSFE']->config['sys_language_uid'] = 0;
//    $GLOBALS['TSFE']->settingLanguage();

    $dimensionMappings = $event->getModule()->getServer()->getDimensionMappings();
    $mappingProblemsOccurred = false;

    $sysLanguageUids = [];
    $defaultDimensionMapping = null;
    $translationDimensionMappings = [];
    foreach ($dimensionMappings as $dimensionMapping) {
      if ($dimensionMapping->getLanguage() === 0) {
        $defaultDimensionMapping = $dimensionMapping;
      } else {
        $sysLanguageUids[] = (int)$dimensionMapping->getLanguage();
        $translationDimensionMappings[] = $dimensionMapping;
      }
    }

    if (!$object) {
      $object = $this->createObject($event);
      //$object->setRemoteId($event->getObjectId());
      $this->persist();
    }

    // Notice: if for some reason - say, if dimensions were not configured - the system contains no dimension which
    // maps to the default language, then $defaultDimensionMapping will be null. Depending on whether or not the
    // remote system has enabled dimensions, the mapping may cause errors (if PIM has dimensions but local system
    // has not configured them, properties cannot map correctly).
    $rootObjectMappingProblemsOccurred = $this->mapPropertiesFromDataToObject($data, $object, $event->getModule(), $defaultDimensionMapping);
    $this->getObjectRepository()->update($object);
    $this->persist();

    $mappingProblemsOccurred = $mappingProblemsOccurred ?: $rootObjectMappingProblemsOccurred;

    if ($defaultDimensionMapping === null || !$event->getModule()->getContainsDimensions()) {
      // This return is in place for TYPO3 configurations that don't contain dimension mapping. If the PIM wants
      // to deliver dimensions but none are configured, errors will most likely have been raised during mapping
      // right before this case - but even in case the mapping actually succeeds with pure null values, we put
      // a return here because there is no need to continue mapping dimensions to translations.
      // Alternative case is that dimension mapping is disabled for the specific module, which is possible to do
      // if the PIM service absolutely has no dimensioned data (e.g. the "data" module).
      return $mappingProblemsOccurred;
    }

    $this->session->unregisterObject($object);

    foreach ($translationDimensionMappings as $translationDimensionMapping) {

      if (!$translationDimensionMapping->isActive()) {
        $this->loggingService->logObjectActivity(
          $data['result'][0]['id'],
          'Dimension mapping ' . $translationDimensionMapping->getUid() . ' is configured to not use dimensions, skipping translated version!',
          'sys_language_uid',
          1 //GeneralUtility::SYSLOG_SEVERITY_INFO
        );
        continue;
      }

      #$persistenceSession->destroy();
      $languageUid = $translationDimensionMapping->getLanguage();

      /*
      $GLOBALS['TSFE']->sys_language_content = $languageUid;
      $GLOBALS['TSFE']->sys_page->sys_language_uid = $languageUid;
      $GLOBALS['TSFE']->config['sys_language_uid'] = $languageUid;
      $GLOBALS['TSFE']->settingLanguage();
      */
      /**  @see .build/vendor/typo3/cms-core/Documentation/Changelog/8.0/Breaking-72424-RemovedDeprecatedTypoScriptFrontendControllerOptionsAndMethods.rst */
//      $GLOBALS['TSFE'] = new FrontendTypoScript($GLOBALS['TYPO3_CONF_VARS'], [$event->getModule()->getStoragePid(), 0]);
//      $GLOBALS['TSFE']->sys_page = new PageRepository();
//      $GLOBALS['TSFE']->sys_page->sys_language_uid = $languageUid;
//      $GLOBALS['TSFE']->getPageAndRootline();
//      $GLOBALS['TSFE']->sys_language_content = $languageUid;
//      $GLOBALS['TSFE']->config['sys_language_uid'] = 0;
//      $GLOBALS['TSFE']->settingLanguage();

      $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->getTableName());
      $existingRowResult = $queryBuilder->select('uid', 'l10n_parent', 'sys_language_uid')
        ->from($this->getTableName())
        ->where($queryBuilder->expr()->eq('sys_language_uid', $languageUid))
        ->andWhere($queryBuilder->expr()->eq('l10n_parent', $object->getUid()))
        ->executeQuery();
      try {
        $existingRow = $existingRowResult->fetchOne();
        $translationObject = $this->createObject($event, $languageUid, $object->getUid(), $existingRow);
        //$translationObject->setRemoteId($event->getObjectId());
        $objectMappingProblemsOccurred = $this->mapPropertiesFromDataToObject($data, $translationObject, $event->getModule(), $translationDimensionMapping);
        $mappingProblemsOccurred = $mappingProblemsOccurred ?: $objectMappingProblemsOccurred;
        $this->getObjectRepository()->update($translationObject);
        $this->persist();
        $this->session->unregisterObject($translationObject);
      } catch (Exception $e) {
      }
    }

    #$persistenceSession->destroy();
    #$persistenceSession->registerObject($event, get_class($event) . ':' . $event->getUid());
    $this->session->registerObject($event, get_class($event) . ':' . $event->getUid());

    return $mappingProblemsOccurred;
  }
}
