<?php

namespace Crossmedia\Fourallportal\DynamicModel;

use Crossmedia\Fourallportal\Domain\Model\ComplexType;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Mapping\MappingRegister;
use RuntimeException;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Class DynamicModelGenerator
 *
 * Generates abstract model classes based on an input
 * list of properties. Also handles dynamic schema generation
 * based on the same input.
 */
class DynamicModelGenerator
{
  protected array $automaticSchemaColumns = [
    'uid INT(11) NOT NULL auto_increment',
    'pid INT(11) DEFAULT \'0\' NOT NULL',
    'tstamp INT(11) unsigned DEFAULT \'0\' NOT NULL',
    'crdate INT(11) unsigned DEFAULT \'0\' NOT NULL',
    'cruser_id INT(11) unsigned DEFAULT \'0\' NOT NULL',
    'deleted TINYINT(4) unsigned DEFAULT \'0\' NOT NULL',
    't3ver_oid INT(11) DEFAULT \'0\' NOT NULL',
    't3ver_id INT(11) DEFAULT \'0\' NOT NULL',
    't3ver_wsid INT(11) DEFAULT \'0\' NOT NULL',
    't3ver_label VARCHAR(255) DEFAULT \'\' NOT NULL',
    't3ver_state TINYINT(4) DEFAULT \'0\' NOT NULL',
    't3ver_stage INT(11) DEFAULT \'0\' NOT NULL',
    't3ver_count INT(11) DEFAULT \'0\' NOT NULL',
    't3ver_tstamp INT(11) DEFAULT \'0\' NOT NULL',
    't3ver_move_id INT(11) DEFAULT \'0\' NOT NULL',
    'sys_language_uid INT(11) DEFAULT \'0\' NOT NULL',
    'l10n_state TEXT DEFAULT NULL',
    'l10n_parent INT(11) DEFAULT \'0\' NOT NULL',
    'l10n_diffsource mediumblob',
    'remote_id varchar(64) DEFAULT \'\' NOT NULL',
  ];
  protected array $automaticSchemaKeys = [
    'PRIMARY KEY (uid)',
    'KEY parent (pid)',
    'KEY remote_id (remote_id)',
    'KEY t3ver_oid (t3ver_oid,t3ver_wsid)',
    'KEY language (l10n_parent,sys_language_uid)',
  ];

  /**
   * @param ServerRepository|null $serverRepository
   * @param DataMapper|null $dataMapper
   * @param PersistenceManager|null $persistenceManager
   */
  public function __construct(
    protected ?ServerRepository   $serverRepository,
    protected ?DataMapper         $dataMapper,
    protected ?PersistenceManager $persistenceManager)
  {
  }


  /**
   * @param array $modules
   * @return array
   * @throws Exception
   */
  public function generateSchemasForModules(array $modules): array
  {
    $sqlString = [];
    $manyToManyRelations = [];
    $configuredDynamicModels = DynamicModelRegister::getModelClassNamesRegisteredForAutomaticHandling();
    $configuredDynamicModels = array_combine($configuredDynamicModels, $configuredDynamicModels);

    foreach ($modules as $module) {
      $entityClassName = $module->getMapper()->getEntityClassName();
      $isAutomatedModel = DynamicModelRegister::isModelRegisteredForAutomaticHandling($entityClassName);

      $propertyConfigurations = $this->getPropertyConfigurationFromConnector($module);
      if (empty($propertyConfigurations) && !$isAutomatedModel) {
        continue;
      }

      $tableName = $this->dataMapper->getDataMap($entityClassName)->getTableName();
      if ($isAutomatedModel) {
        unset($configuredDynamicModels[$entityClassName]);
        $lines = $this->automaticSchemaColumns;
      } else {
        $lines = [];
      }

      foreach ($propertyConfigurations as $propertyConfiguration) {
        $dataType = DynamicModelRegister::getOverriddenOrOriginalSqlType($tableName, $propertyConfiguration['column'], $propertyConfiguration['schema']);
        $lines[] = $propertyConfiguration['column'] . ' ' . $dataType;
        if (isset($propertyConfiguration['config']['MM'])) {
          $manyToManyRelations[] = $propertyConfiguration['config']['MM'];
        }
      }

      if ($isAutomatedModel) {
        $lines = array_merge($lines, $this->automaticSchemaKeys);
      }

      $sqlString[] = 'CREATE TABLE ' . $tableName . ' (' . PHP_EOL . implode(',' . PHP_EOL, $lines) . PHP_EOL . ');';
    }

    // Iterate dynamic model classes which were NOT handled by a configured module.
    // This is done to ensure that the schema exists even if the connector module is not yet configured.
    foreach ($configuredDynamicModels as $entityClassName) {
      $tableName = $this->dataMapper->getDataMap($entityClassName)->getTableName();
      $lines = array_merge($this->automaticSchemaColumns, $this->automaticSchemaKeys);
      $sqlString[] = 'CREATE TABLE ' . $tableName . ' (' . PHP_EOL . implode(',' . PHP_EOL, $lines) . PHP_EOL . ');';
    }

    $manyToManyTableTemplate = <<< TEMPLATE

CREATE TABLE %s (
    uid_local int(11) DEFAULT '0' NOT NULL,
    uid_foreign int(11) DEFAULT '0' NOT NULL,
    sorting int(11) DEFAULT '0' NOT NULL,
    sorting_foreign int(11) DEFAULT '0' NOT NULL,

    KEY uid_local_foreign (uid_local,uid_foreign)
);

TEMPLATE;


    // Process all queued MM table creations
    foreach ($manyToManyRelations as $manyToManyTableName) {
      $sqlString[] = sprintf($manyToManyTableTemplate, $manyToManyTableName);
    }

    return $sqlString;
  }

  /**
   * @param array $sqlString
   * @return array
   */
  public function addSchemasForAllModules(array $sqlString): array
  {
    $staticSchemasFromExtensions = [];
    $schemaFromModules = [];
    foreach (DynamicModelRegister::getModelClassNamesRegisteredForAutomaticHandling() as $entityClassName) {
      $entityClassNameParts = explode('\\', $entityClassName);
      $entityClassNameBase = array_slice($entityClassNameParts, 0, -3);
      $extensionName = array_pop($entityClassNameBase);
      $extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored($extensionName);
      if (!isset($staticSchemasFromExtensions[$extensionKey])) {
        $possibleSchemaFile = ExtensionManagementUtility::extPath($extensionKey) . 'Configuration/SQL/DynamicSchema.sql';
        if (is_file($possibleSchemaFile)) {
          $staticSchemasFromExtensions[$extensionKey] = file_get_contents($possibleSchemaFile);
        }
      }
    }
    return array_merge(
      $sqlString,
      $schemaFromModules,
      $staticSchemasFromExtensions
    );
  }

  /**
   * @param string $extensionKey
   * @param string $tableName
   * @return string
   */
  protected static function findIconFile(string $extensionKey, string $tableName): string
  {
    $extensions = ['svg', 'png', 'jpg', 'jpeg', 'gif'];
    $detectedFiles = GeneralUtility::getFilesInDir(
      ExtensionManagementUtility::extPath($extensionKey, 'Resources/Public/Icons/'),
      implode(',', $extensions)
    );
    foreach ($detectedFiles as $file) {
      if (in_array(pathinfo($file, PATHINFO_EXTENSION), $extensions)) {
        return $file;
      }
    }

    return $tableName . '.svg';
  }

  /**
   * @param string $modelClassName
   * @param bool $readOnly If TRUE, generates TCA fields as read-only
   * @return array
   * @throws ApiException
   * @throws Exception
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public static function generateAutomaticTableConfigurationForModelClassName(string $modelClassName, bool $readOnly = false): array
  {
    $modelClassNameParts = explode('\\', substr($modelClassName, 0, strpos($modelClassName, '\\Domain\\Model\\')));
    $extensionName = array_pop($modelClassNameParts);
    $extensionKey = GeneralUtility::camelCaseToLowerCaseUnderscored($extensionName);
    $tableName = GeneralUtility::makeInstance(DataMapper::class)->getDataMap($modelClassName)->getTableName();

    $tca = include ExtensionManagementUtility::extPath('fourallportal', 'Configuration/TCA/BoilerPlate/AutomaticTableConfiguration.php');
    $additionalColumns = DynamicModelGenerator::generateTableConfigurationForModuleIdentifiedByModelClassName($modelClassName, $readOnly);
    $additionalColumnNames = implode(',', array_keys($additionalColumns));
    $detectedIconFile = static::findIconFile($extensionKey, $tableName);
    $tca['columns'] = array_replace($additionalColumns, $tca['columns']);
    $tca['interface']['showRecordFieldList'] .= ',' . $additionalColumnNames;
    $tca['types']['1']['showitem'] .= ',' . $additionalColumnNames;
    $tca['columns']['l10n_parent']['config']['foreign_table'] = $tableName;
    $tca['columns']['l10n_parent']['config']['foreign_table_where'] = str_replace(
      '###TABLE###',
      $tableName,
      $tca['columns']['l10n_parent']['config']['foreign_table_where']
    );
    $tca['ctrl']['label'] = key($additionalColumns);
    $tca['ctrl']['iconfile'] = 'EXT:' . $extensionKey . '/Resources/Public/Icons/' . $tableName . '.' . pathinfo($detectedIconFile, PATHINFO_EXTENSION);
    $tca['ctrl']['title'] = 'LLL:EXT:' . $extensionKey . '/Resources/Private/Language/locallang.xlf:' . $tableName;
    return $tca;
  }

  /**
   * @param string $modelClassName
   * @param bool $readOnly If TRUE, generates TCA fields as read-only
   * @return array
   * @throws ApiException
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public static function generateTableConfigurationForModuleIdentifiedByModelClassName(string $modelClassName, bool $readOnly = false): array
  {
    $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
    $cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);

    $columns = [];
    foreach (GeneralUtility::makeInstance(static::class)->getAllConfiguredModules() as $module) {
      if ($module->getMapper()->getEntityClassName() === $modelClassName) {
        $propertyConfigurations = (new static())->getPropertyConfigurationFromConnector($module);
        foreach ($propertyConfigurations as $propertyConfiguration) {
          if ($readOnly) {
            $propertyConfiguration['config']['readOnly'] = true;
          }
          $columns[$propertyConfiguration['column']] = [
            'label' => 'Automatic model field: ' . $propertyConfiguration['column'],
            'exclude' => true,
            'config' => $propertyConfiguration['config']
          ];
        }
      }
    }
    return $columns;
  }

  /**
   * @param array $parameters
   * @return void
   */
  public function regenerateModelsAfterCacheFlush(array $parameters): void
  {
    if (in_array($parameters['cacheCmd'] ?? false, ['all', 'system'])) {
      $this->generateAbstractModelsForAllModules();
    }
  }

  /**
   * @param boolean $safeMode
   * @return void
   * @throws ApiException
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function generateAbstractModelsForAllModules(bool $safeMode = false): void
  {
    // Loop 1: generate fallbacks
    foreach ($this->getAllConfiguredModules() as $module) {
      if (!$module->isEnableDynamicModel()) {
        continue;
      }
      if (class_exists($module->getMapper()->getEntityClassName()) || class_exists($module->getMapper()->getEntityClassName())) {
        continue;
      }
      $this->generateAbstractModelForModule($module, false, true);
    }
    if ($safeMode === false) {
      // Loop 2: generate actual classes, which require the presence of the fallback in order for the data map to work
      foreach ($this->getAllConfiguredModules() as $module) {
        if (!$module->isEnableDynamicModel() || class_exists($module->getMapper()->getEntityClassName())) {
          continue;
        }
        $this->generateAbstractModelForModule($module);
      }
    }
  }

  /**
   * Generates one abstract model class based on properties
   * of the Module. Generates the class in two steps:
   *
   * 1. A completely safe fallback that has none of the
   *    properties read from the remote API, generated
   *    before any of the properties are analysed. If a
   *    property can't be converted to safe PHP/SQL then
   *    the generation exists and the fallback is kept.
   * 2. The actual class with all properties as read from
   *    the remote API.
   *
   * The two-step generation ensures that even if the second
   * step with dynamic properties fail (perhaps due to logic
   * errors or connectivity issues) a safe fallback is
   * guaranteed to exist and be loadable through the
   * loadAbstractClass() method on this class.
   *
   * @param Module $module
   * @param boolean $strict If TRUE, generates strict PHP code
   * @param boolean $asFallback
   * @return string
   * @throws ApiException
   */
  public function generateAbstractModelForModule(Module $module, bool $strict = false, bool $asFallback = false): string
  {
    $repository = $module->getMapper()->getObjectRepository();

    $entityClassName = substr(str_replace('\\Domain\\Repository\\', '\\Domain\\Model\\', get_class($repository)), 0, -10);
    $entityClassNameParts = explode('\\', $entityClassName);
    $classNameWithoutNamespace = array_pop($entityClassNameParts);
    $abstractModelClassName = implode('\\', $entityClassNameParts) . '\\Abstract' . $classNameWithoutNamespace;

    if ($asFallback) {
      // Phase 1: create a safe fallback that has none of the properties returned from the API.
      // This class is generated at a time in the process where errors based on remote API data
      // have not yet been raised - as long as the local code base is intact, this class can be
      // generated and trusted.
      $sourceCode = $this->generateCachedClassFile($abstractModelClassName, AbstractEntity::class, [], $strict, sha1($abstractModelClassName) . '_fallback');

    } else {
      // Phase 2: the more risky dynamic model with properties read from the remote API. If any
      // properties returned from the API are unsupported or otherwise can't be expressed as safe
      // PHP/SQL representations, either errors will be raised or the remaining safe properties
      // will be written - depending on the context of the TYPO3 site (Development = errors thrown)
      $propertyConfiguration = $this->getPropertyConfigurationFromConnector($module);

      $propertyConfiguration['remoteId'] = [
        "column" => "remote_id",
        "type" => "string",
        "schema" => "varchar(64) default ''",
        "config" => [
          "type" => "input",
          "size" => 64
        ]
      ];
      $propertyConfiguration['l10nParent'] = [
        "column" => 'l10n_parent',
        "type" => '\\' . $entityClassName,
        "schema" => "INT(11) DEFAULT '0' NOT NULL",
        "config" => [
          "type" => "passthrough"
        ]
      ];

      $sourceCode = $this->generateCachedClassFile($abstractModelClassName, AbstractEntity::class, $propertyConfiguration, $strict);
    }
    return $sourceCode;
  }

  /**
   * @param string $className
   * @param boolean $safe
   * @throws RuntimeException
   */
  public static function loadAbstractClass(string $className, bool $safe = true): void
  {
    // The first check allows autoload to happen. This is done in order to make it possible to
    // opt out from the dynamic models by simply adding your own base class which uses this name.
    if (class_exists($className)) {
      return;
    }
    $identifier = sha1($className);
    $cache = static::getGeneratedClassCache();
    $cache->requireOnce($identifier);

    if (!class_exists($className, false)) {
      // We will attempt to recreate *all* model classes now since one missing class very likely
      // means all classes are missing. However, this may not be possible to do at the time when
      // this code is executed in the runtime - so we catch and suppress errors and instead throw
      // a RuntimeException asking the administrator to manually regenerate the classes. Note that
      // when this exception is thrown, simply calling another piece of code may cause the classes
      // to be regenerated correctly.
      try {
        GeneralUtility::makeInstance(static::class)->generateAbstractModelsForAllModules($safe);
        $cache->requireOnce($identifier . '_fallback');
      } catch (RuntimeException $error) {
        // Suppressed, see above.
      }
    }

    if (!class_exists($className, false)) {

      try {
        $cache->requireOnce($identifier . '_fallback');
        // Attempt to load the fallback class which should be completely safe and always present even if
        // the generated class with properties from the remote API could not be loaded. Check the TYPO3
        // application context first of all - if we are in Development context, throw an error instead of
        // silently allowing the base class to load. This is done in order to protect Production systems
        // from potentially uncaught exceptions or complete failures when trying to use the models.
        // Using the model classes is then possible, but none of the dynamic properties can be retrieved.
        if (Environment::getContext()->isDevelopment()) {
          throw new RuntimeException(
            sprintf(
              'Attempting to load the dynamically generated class "%s" caused the fallback class to be ' .
              'resolved. Since your TYPO3 site is in Development application context you see this error ' .
              'to inform you that there is a possible lack of support for the returned properties that you ' .
              'should address in the code before you let it deploy to production.',
              $className
            ), 1865166438
          );
        }
      } catch (RuntimeException $error) {
        // Suppressed; if even the fallback class can't load, the standard reason below will be given.
      }
    }

    // Final check - if the class wasn't loaded by now, that's a fatal error.
    if (!class_exists($className, false)) {
      throw new RuntimeException(
        sprintf('Dynamic Fourall class "%s" could not be loaded, please regenerate classes!', $className), 2136459203
      );
    }
  }

  /**
   * Return an array of for example:
   *
   *     ['propertyNameInModel' => ['type' => 'string', 'column' => 'sql_column_name', 'schema' => 'varchar(255) default \'\' not null', 'config' => $tcaConfigArray]
   *
   * - one for each of the properties returned from the remote API.
   *
   * @param Module $module
   * @return array
   * @throws ApiException
   */
  protected function getPropertyConfigurationFromConnector(Module $module)
  {
    $properties = [];
    $moduleConfiguration = $module->getModuleConfiguration();
    $connectorConfiguration = $module->getConnectorConfiguration();
    $entityClassName = $module->getMapper()->getEntityClassName();

    $fieldsAndRelations = array_replace_recursive(
      array_intersect_assoc(
        $moduleConfiguration['field_conf'],
        $connectorConfiguration['fieldsToLoad']
      ),
      $moduleConfiguration['relation_conf']
    );

    uasort($fieldsAndRelations, function ($a, $b) {
      //return ($a['field'] ?? $a['name']) <=> ($b['field'] ?? $b['name']);
      return $a['name'] <=> $b['name'];
    });

    $validModuleNames = [];
    foreach ($module->getServer()->getModules() as $configuredModule) {
      $validModuleNames[] = $configuredModule->getModuleName();
    }

    foreach ($fieldsAndRelations as $originalName => $fieldConfiguration) {

      // We need to reset a possibly overridden types so they use the *module* configuration's list of fields and
      // types as the actual type of the field. This is important to the subsequent logic!
      if (!empty($moduleConfiguration['field_conf'][$originalName]['type'])) {
        $fieldConfiguration['type'] = $moduleConfiguration['field_conf'][$originalName]['type'];
      }

      //$fieldName = $fieldConfiguration['field'] ?? $fieldConfiguration['name'];
      //$fieldName = $this->resolveFieldName($fieldConfiguration);
      $fieldName = $originalName;
      $propertyName = GeneralUtility::underscoredToLowerCamelCase($fieldName);
      echo 'Generating: ' . $module->getModuleName() . '->' . $entityClassName . '->' . $propertyName;

      try {
        if ($this->isSkippedField($module, $originalName, $fieldConfiguration, $validModuleNames)) {
          continue;
        }

        list ($type, $schema, $tca) = $this->guessLocalTypesFromRemoteField($originalName, $fieldConfiguration, $module->getModuleName());

        echo '(' . $fieldConfiguration['type'] . ', ' . $type . ', ' . $schema . ')';

        $properties[$propertyName] = [
          'column' => $fieldName,
          'type' => $type,
          'schema' => $schema,
          'config' => $tca
        ];
      } catch (UndefinedModuleException $error) {
        echo ' !!!SKIPPED!!! ' . $error->getMessage();
      } catch (RuntimeException $error) {
        echo ' !!!SKIPPED!!! ' . $error->getMessage();
        if (Environment::getContext()->isDevelopment()) {
          throw $error;
        }
      }

      echo PHP_EOL;
    }

    return $properties;
  }

  /**
   * @param string $originalName
   * @param array $fieldConfiguration
   * @param string $currentSideModuleName Module name for the side of the relation we are currently processing
   * @return array
   * @throws Exception
   */
  protected function guessLocalTypesFromRemoteField(string $originalName, array $fieldConfiguration, $currentSideModuleName)
  {
    $textFieldTypes = ['CEText', 'MAMString', 'XMPString'];
    if (array_key_exists('fulltext', $fieldConfiguration) || in_array($fieldConfiguration['type'], $textFieldTypes)) {
      // Shortcut: any fulltext/text typed fields will be "string" in class property and "text" in SQL
      return ['string', 'text', ['type' => 'text']];
    }

    //$fieldName = $this->resolveFieldName($fieldConfiguration);
    $fieldName = $originalName;

    $dataType = $sqlType = null;
    $tca = [
      'type' => 'passthrough'
    ];

    switch ($fieldConfiguration['type']) {
      case 'CEVarchar':
        $dataType = 'string';
        $sqlType = 'text';
        $tca = [
          'type' => 'input',
          'size' => $fieldConfiguration['length']
        ];
        break;
      case 'MAMDate':
      case 'CEDate':
        $dataType = '\\DateTime';
        $sqlType = 'int(11) default 0 NOT NULL';
        $tca = [
          'type' => 'input'
        ];
        break;
      case 'MAMBoolean';
      case 'CEBoolean':
        $dataType = 'boolean';
        $sqlType = 'int(1) default 0 NOT NULL';
        $tca = [
          'type' => 'check'
        ];
        break;
      case 'CEDouble':
        $dataType = 'float';
        $sqlType = 'double(10,6) default 0.0 NOT NULL';
        $tca = [
          'type' => 'input'
        ];
        break;
      case 'CELong':
        $dataType = 'int';
        $sqlType = 'bigint(20) default 0 NOT NULL';
        $tca = [
          'type' => 'input',
          'eval' => 'trim,int'
        ];
        break;
      case 'CETimestamp':
      case 'CEInteger':
      case 'MAMNumber':
      case 'XMPNumber':
        $dataType = 'int';
        $sqlType = 'int(11) default 0 NOT NULL';
        $tca = [
          'type' => 'input',
          'eval' => 'trim,int'
        ];
        break;
      case 'MAMList':
      case 'CEVarcharList':
        $dataType = 'array';
        $sqlType = 'text';
        break;
      case 'FIELD_LINK':
      case 'CEExternalIdList':
      case 'CEIdList':
      case 'MANY_TO_MANY':
      case 'ONE_TO_MANY':
      case 'MANY_TO_ONE':
        //case 'ONE_TO_ONE':
      case 'CEExternalId':
        $modules = $this->getAllConfiguredModules();
        if (!empty($fieldConfiguration['modules'])) {
          $relatedModule = reset($fieldConfiguration['modules']);
        } elseif (!empty($fieldConfiguration['relatedModule'])) {
          $relatedModule = $fieldConfiguration['relatedModule'];
        } elseif (($fieldConfiguration['parent'] ?? false) === $currentSideModuleName) {
          $relatedModule = $fieldConfiguration['child'];
        } else {
          $relatedModule = $fieldConfiguration['parent'];
        }
        if (!isset($modules[$relatedModule])) {
          throw new UndefinedModuleException('Property "' . $fieldName . '" points to module "' . $relatedModule . '" which is not defined', 8646815387);
        }
        $entityNameParent = $modules[$currentSideModuleName]->getMapper()->getEntityClassName();
        $tableNameParent = $this->dataMapper->getDataMap($entityNameParent)->getTableName();
        $entityClassName = $modules[$relatedModule]->getMapper()->getEntityClassName();
        $tca = $this->determineTableConfigurationForRelation($fieldName, $fieldConfiguration, $currentSideModuleName, $tableNameParent);
        $dataType = '\\' . ObjectStorage::class . '<\\' . $entityClassName . '>';
        $sqlType = 'int(11) default 0 NOT NULL';
        break;
      case 'CEId':
        //case 'CEExternalId':
      case 'ONE_TO_ONE':
        $modules = $this->getAllConfiguredModules();
        if (!empty($fieldConfiguration['modules'])) {
          $relatedModule = reset($fieldConfiguration['modules']);
        } elseif (!empty($fieldConfiguration['relatedModule'])) {
          $relatedModule = $fieldConfiguration['relatedModule'];
        } elseif (($fieldConfiguration['parent'] ?? false) === $currentSideModuleName) {
          $relatedModule = $fieldConfiguration['child'];
        } else {
          $relatedModule = $fieldConfiguration['parent'];
        }
        if (!isset($modules[$relatedModule])) {
          throw new UndefinedModuleException('Property "' . $fieldName . '" points to module "' . $relatedModule . '" which is not defined', 2321076760);
        }
        $entityNameParent = $modules[$currentSideModuleName]->getMapper()->getEntityClassName();
        $tableNameParent = $this->dataMapper->getDataMap($entityNameParent)->getTableName();
        $entityClassName = $entityClassName ?? $modules[$relatedModule]->getMapper()->getEntityClassName();
        $tca = $this->determineTableConfigurationForRelation($fieldName, $fieldConfiguration, $currentSideModuleName, $tableNameParent);
        if ($relatedModule === 'file') {
          $dataType = '\\' . ObjectStorage::class . '<\\' . $entityClassName . '>';
        }
        $dataType = $dataType ?? ('\\' . $entityClassName);
        $sqlType = 'int(11) default 0 NOT NULL';
        break;
      default:
        break;
    }

    if (!$dataType && !$sqlType) {
      // The field was not of a standard type and is most likely a "ComplexType".

      $modules = $this->getAllConfiguredModules();
      $entityNameParent = $modules[$currentSideModuleName]->getMapper()->getEntityClassName();
      $entityShortNameParent = GeneralUtility::camelCaseToLowerCaseUnderscored(substr($entityNameParent, strrpos($entityNameParent, '\\') + 1));
      $tableNameParent = $this->dataMapper->getDataMap($entityNameParent)->getTableName();
      $dataType = '\\' . ComplexType::class;
      $sqlType = 'int(11) default 0 NOT NULL';
      $tca = [
        'type' => 'select',
        'foreign_table' => 'tx_fourallportal_domain_model_complextype',
        'size' => 1,
        //'foreign_field' => 'parent_uid',
        //'foreign_match_fields' => [
        //    'table_name' => $tableNameParent,
        //    'field_name' => $fieldName,
        //],
        //'foreign_table_field' => 'table_name',
        //'foreign_table_field' => $entityShortNameParent,
        'maxitems' => 1
      ];
    }

    return [
      $dataType,
      $sqlType,
      $tca
    ];
  }

  /**
   * Attempts to determine a valid TCA configuration expressing
   * a relationship (which is required for TYPO3 to handle the
   * relation correctly).
   *
   * A valid relation is determined by the presence of a connector
   * in the TYPO3 system which handles the module associated with
   * the relation. If such a connector exists, it contains the
   * information necessary to determine which object the relation
   * points to and which relationship type it uses - and thus it
   * becomes possible to create the TCA that is required.
   *
   * If a relation is considered invalid due to a missing connector
   * an Exception is thrown (and the error is either reported or
   * ignored and the property skipped, depending on TYPO3 context).
   *
   * @param string $originalName
   * @param array $fieldConfiguration
   * @param string $currentSideModuleName Module name for the side of the relation we are currently processing
   * @param string $currentTableName
   * @return array
   * @throws Exception
   */
  protected function determineTableConfigurationForRelation(string $originalName, array $fieldConfiguration, $currentSideModuleName, $currentTableName)
  {
    $overriddenType = null;
    $modules = $this->getAllConfiguredModules();

    //$fieldName = $this->resolveFieldName($fieldConfiguration);
    $fieldName = $originalName;

    if (!empty($fieldConfiguration['modules'])) {
      $relatedModule = reset($fieldConfiguration['modules']);
    } elseif (!empty($fieldConfiguration['relatedModule'])) {
      $relatedModule = $fieldConfiguration['relatedModule'];
    } elseif (($fieldConfiguration['parent'] ?? false) === $currentSideModuleName) {
      $relatedModule = $fieldConfiguration['child'];
    } else {
      $relatedModule = $fieldConfiguration['parent'];
    }

    //if ($fieldConfiguration['relatedModule'] ?? false) {
    if ($fieldConfiguration['type'] === 'CEExternalId') {
      $overriddenType = 'ONE_TO_ONE';
    } elseif ($fieldConfiguration['type'] === 'CEExternalIdList') {
      $overriddenType = 'MANY_TO_MANY';
    }
    //}

    try {
      $tableNameParent = $entityNameParent = $entityShortNameParent = null;
      if ($fieldConfiguration['parent'] ?? false) {
        $this->validatePresenceOfConfiguredConnectorForModule($fieldConfiguration['parent']);
        $entityNameParent = $modules[$fieldConfiguration['parent']]->getMapper()->getEntityClassName();
        $entityShortNameParent = substr($entityNameParent, strrpos($entityNameParent, '\\') + 1);
        $tableNameParent = $this->dataMapper->getDataMap($entityNameParent)->getTableName();
      }
    } catch (RuntimeException $error) {
      throw new UndefinedModuleException(
        sprintf(
          'Field "%s" uses module "%s" as parent in a relation, but the parent module is unknown to TYPO3. ' .
          'Please either configure a connector that uses the module, create a manual mapping for the field ' .
          'or configure the field as ignored by the mapper.',
          $fieldName,
          $fieldConfiguration['parent']
        ), 4816263978
      );
    }

    try {
      $this->validatePresenceOfConfiguredConnectorForModule($relatedModule);
      $entityNameChild = $modules[$relatedModule]->getMapper()->getEntityClassName();
      $tableNameChild = $this->dataMapper->getDataMap($entityNameChild)->getTableName();
    } catch (RuntimeException $error) {
      throw new UndefinedModuleException(
        sprintf(
          'Field "%s" uses module "%s" as child in a relation, but the child module is unknown to TYPO3. ' .
          'Please either configure a connector that uses the module, create a manual mapping for the field ' .
          'or configure the field as ignored by the mapper.',
          $fieldName,
          $fieldConfiguration['child']
        ), 9274744073
      );
    }

    $tca = [
      'type' => 'select',
      'renderType' => 'selectSingle',
      'foreign_table' => $tableNameChild ?? $tableNameParent
    ];

    $fieldType = $overriddenType ?? $fieldConfiguration['type'];

    switch ($fieldType) {

      // M:N is expressed to TYPO3 as any other relation, but having an "MM" entry in the TCA containing a table name.
      // This table can then be generated on-the-fly since all MM tables have the same default structure when written
      // by this model generator class.
      case 'CEExternalIdList':
      case 'FIELD_LINK':
      case 'CEIdList':
      case 'MANY_TO_MANY':
        $tca['type'] = 'group';
        $tca['internal_type'] = 'db';
        $tca['allowed'] = $tableNameChild ?? $tableNameParent;
        $tca['MM'] = 'tx_fourallportal_' . str_replace('.', '_', ($fieldName)) . '_mm';
        unset($tca['renderType']);

        // Set "MM_opposite_field" to indicate this M:N is mirrored by other TCA. To do so, we must determine if
        // we are currently on the child side of the relation, in which case our field name comes from the child
        // entity name, and comes from parent if the opposite is true.
        if ($currentSideModuleName !== $fieldConfiguration['child']) {
          $tca['MM_opposite_field'] = GeneralUtility::camelCaseToLowerCaseUnderscored($fieldName);
        }
        break;

      // 1:N is expressed by setting a "foreign_field" to be used when matching records. In a 1:1 relation the "uid"
      // column will always be used, but for 1:N we need to choose a different field. Which field this is, is
      // determined by the entity name on the local side of the relation, e.g. if the parent is a class whose short
      // name is "Product", the chosen column name will be "product"; if "ProductCategory" the chosen field name
      // is "product_category" and so on. We include both 1:N and N:1 since to TYPO3 these are technically the same
      // type, but expressing the "symmetric field" on the opposite side of the relation. We determine the target
      // entity names by analyzing which Modules have Connectors that use Mappers which handle the entities.
      case 'MANY_TO_ONE':
      case 'ONE_TO_MANY':
        $tca['foreign_field'] = GeneralUtility::camelCaseToLowerCaseUnderscored($entityShortNameParent) ?: $currentSideModuleName;
        //$tca['symmetric_field'] = GeneralUtility::camelCaseToLowerCaseUnderscored($entityShortNameChild);
        break;

      // Fallback case; ONE_TO_ONE is the default type of relation
      case 'ONE_TO_ONE':
      default:
        break;
    }

    /*
    if (($tca['foreign_table'] ?? null) === 'sys_file_reference' && $fieldType === 'ONE_TO_ONE') {
        $tca['type'] = 'inline';
        $tca = array_merge(
            $tca,
            [
                'foreign_table' => 'sys_file_reference',
                'foreign_field' => 'uid_foreign',
                'foreign_sortby' => 'sorting_foreign',
                'foreign_table_field' => 'tablenames',
                'foreign_match_fields' =>
                    [
                        'fieldname' => 'product_images',
                        'tablenames' => 'tx_syzygyproducts_domain_model_productdetail',
                        'table_local' => 'sys_file',
                    ],
                'foreign_label' => 'uid_local',
                'foreign_selector' => 'uid_local',
            ]
        );
        unset($tca['renderType']);
    } else
    */
    if (($tca['foreign_table'] ?? null) === 'sys_file_reference') {
      $tca = [
          ### !!! Watch out for fieldName different from columnName
          'type' => 'file',
          'appearance' => [
            'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:media.addFileReference'
          ],
          'foreign_types' => [
            '0' => [
              'showitem' => '
                                --palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette'
            ],
            File::FILETYPE_TEXT => [
              'showitem' => '
                                --palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette'
            ],
            File::FILETYPE_IMAGE => [
              'showitem' => '
                                --palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette'
            ],
            File::FILETYPE_AUDIO => [
              'showitem' => '
                                --palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette'
            ],
            File::FILETYPE_VIDEO => [
              'showitem' => '
                                --palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette'
            ],
            File::FILETYPE_APPLICATION => [
              'showitem' => '
                                --palette--;LLL:EXT:lang/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette'
            ]
          ],
          //'maxitems' => $fieldType  === 'ONE_TO_ONE' ? 1 : 99999,
          'foreign_match_fields' => [
            'fieldname' => $fieldConfiguration['field'],
            'tablenames' => $currentTableName,
          ],
      ];
    }

    if (!($tca['foreign_table'] ?? false) && ($tca['type'] ?? false) !== 'group') {
      throw new UndefinedModuleException(
        sprintf(
          'Field "%s" defines a CEExternalId or CEExternalIdList which does not configure a related module. ' .
          'Normally this would mean that this field should be mapped to a plain string value, but due to the ' .
          'ambiguity in target resource type, we require that you manually map or ignore this particular field.',
          $fieldConfiguration['field']
        ), 5649592310
      );
    }

    return $tca;
  }

  /**
   * @param string $moduleName
   * @throws UndefinedModuleException
   */
  protected function validatePresenceOfConfiguredConnectorForModule($moduleName)
  {
    if (empty($this->getAllConfiguredModules()[$moduleName])) {
      throw new UndefinedModuleException(sprintf('Module "%s" is unknown to TYPO3, make sure it is configured!', $moduleName), 8654469181);
    }
  }

  /**
   * Generates the actual class file using templates.
   *
   * Actually generates two different classes which use
   * the same name, but have different priorities:
   *
   * 1. A completely safe fallback that has none of the
   *    properties read from the remote API.
   * 2. The actual class with all properties as read from
   *    the remote API.
   *
   * The two-step generation ensures that even if the second
   * step with dynamic properties fail (perhaps due to logic
   * errors or connectivity issues) a safe fallback is
   * guaranteed to exist and be loadable. The second step
   * simply overwrites the generated fallback if no
   *
   * @param string $className
   * @param string $parentClass
   * @param array $propertyConfiguration
   * @param string $identifier
   * @param bool $strict
   * @return string
   */
  protected function generateCachedClassFile($className, $parentClass, array $propertyConfiguration, $strict = false, $identifier = null)
  {
    return $strict
      ? $this->generateStrictCachedClassFile($className, $parentClass, $propertyConfiguration, $identifier)
      : $this->generateRelaxedCachedClassFile($className, $parentClass, $propertyConfiguration, $identifier);
  }

  protected function generateVirtualArrayGetter($propertyName, $strict = false)
  {
    $getterTemplate = <<< TEMPLATE
    /**
     * @return array
     */
    public function get%sArray()%s
    {
        return (array)json_decode(\$this->%s ?? '', true);
    }


TEMPLATE;
    $strictReturn = '';
    if ($strict) {
      $strictReturn = ': array';
    }
    return sprintf(
      $getterTemplate,
      ucfirst($propertyName),
      $strictReturn,
      $propertyName
    );
  }

  protected function generateRelaxedCachedClassFile($className, $parentClass, array $propertyConfiguration, $identifier = null)
  {
    $propertyTemplate = <<< TEMPLATE

    /**
     * @var %s%s
     */
    protected \$%s = %s;

    public function get%s()
    {
        return \$this->%s;
    }

    /**
     * @param %s %s
     */
    public function set%s(%s)
    {
        \$this->%s = %s;
    }

%s
TEMPLATE;

    $functionsAndProperties = '';
    $objectStorageInitializations = '';
    foreach ($propertyConfiguration as $propertyName => $property) {

      $returnType = $property['type'];
      $virtualArrayGetter = '';
      if ($property['type'] === 'array') {
        // Arrays will be stored as strings and are, technically, strings in the model property,
        // but will have a virtual getter method that returns a json_decode()'d value.
        $virtualArrayGetter = $this->generateVirtualArrayGetter($propertyName);
        $returnType = 'string';
      }

      $lazy = false;
      if (class_exists($returnType) || strpos($returnType, 'ObjectStorage') !== false) {
        $lazy = DynamicModelRegister::isLazyProperty($className, $propertyName);
      }
      $isSingleObjectRelation = is_a(trim($property['type'], '?'), AbstractDomainObject::class, true);

      $upperCasePropertyName = ucfirst($propertyName);
      $functionsAndProperties .= sprintf(
        $propertyTemplate,
        $returnType,
        $lazy ? PHP_EOL . '     * @lazy' : '',
        $propertyName,
        ($property['default'] ?? null) === null ? 'null' : var_export($property['default'], true),
        $upperCasePropertyName,
        $propertyName,
        $returnType,
        '$' . $propertyName,
        $upperCasePropertyName,
        '$' . $propertyName . ($isSingleObjectRelation ? ' = null' : ''),
        $propertyName,
        '$' . $propertyName,
        $virtualArrayGetter
      );
      if (strpos($returnType, '\\Persistence\\ObjectStorage<') !== false) {
        $objectStorageInitializations .= '        $this->' . $propertyName . ' = new \\TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage();' . PHP_EOL;
      }
    }

    $classTemplate = <<< TEMPLATE
namespace %s;

use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;

class %s extends %s
{
%s    public function __construct()
    {
        \$this->initializeStorageObjects();
    }

    public function initializeStorageObjects()
    {
%s
    }
}
TEMPLATE;
    $classNameParts = explode('\\', $className);
    $classShortName = array_pop($classNameParts);
    $namespace = implode('\\', $classNameParts);

    $classSourceCode = sprintf(
      $classTemplate,
      $namespace,
      $classShortName,
      '\\' . ltrim($parentClass, '\\'),
      $functionsAndProperties,
      rtrim($objectStorageInitializations)
    );

    $identifier = $identifier ?: sha1($className);
    static::getGeneratedClassCache()->set($identifier, $classSourceCode);
    return $classSourceCode;
  }

  protected function generateStrictCachedClassFile($className, $parentClass, array $propertyConfiguration, $identifier = null)
  {
    $propertyTemplate = <<< TEMPLATE
    /**
     * @var %s%s
     */
    protected \$%s = %s;

    public function get%s()%s
    {
        return \$this->%s;
    }

    public function set%s(%s%s)
    {
        \$this->%s = %s;
    }

%s
TEMPLATE;

    $functionsAndProperties = '';
    $objectStorageInitializations = '';

    foreach ($propertyConfiguration as $propertyName => $property) {
      $variableType = $property['type'];
      if (strpos($property['type'], '\\Persistence\\ObjectStorage<') !== false) {
        $objectStorageInitializations .= '        $this->' . $propertyName . ' = new \\TYPO3\\CMS\\Extbase\\Persistence\\ObjectStorage();' . PHP_EOL;
        $returnType = '\\' . ObjectStorage::class;
      } elseif (class_exists($property['type'])) {
        $returnType = '?' . $property['type'];
      } else {
        $returnType = '';
      }
      if (in_array($property['type'], ['int', 'float', 'double', 'string', 'bool'])) {
        settype($property['default'], $property['type']);
        $returnType = $property['type'];
      }

      $virtualArrayGetter = '';
      if ($variableType === 'array') {
        // Arrays will be stored as strings and are, technically, strings in the model property,
        // but will have a virtual getter method that returns a json_decode()'d value.
        $virtualArrayGetter = $this->generateVirtualArrayGetter($propertyName, true);
        $returnType = '?string';
        $variableType = 'string';
      } elseif ($variableType === 'string') {
        $returnType = '?string';
      }

      $defaultValueExpression = ($property['default'] ?? null) === null ? 'null' : var_export($property['default'], true);

      $isLazyProperty = false;
      if (class_exists(trim($returnType, '?\\'))) {
        $isLazyProperty = DynamicModelRegister::isLazyProperty($className, $propertyName);
      }
      $isSingleObjectRelation = is_a(trim($property['type'], '?'), AbstractDomainObject::class, true);
      $isLazySingleObjectRelation = $isLazyProperty && $isSingleObjectRelation;

      $upperCasePropertyName = ucfirst($propertyName);
      $functionsAndProperties .= sprintf(
        $propertyTemplate,
        $variableType,
        $isLazyProperty ? PHP_EOL . '     * @lazy' : '',
        $propertyName,
        $defaultValueExpression,
        $upperCasePropertyName,
        $returnType ? ': ' . $returnType : '',
        $propertyName . ($isLazySingleObjectRelation ? ' instanceof LazyLoadingProxy ? $this->' . $propertyName . '->_loadRealInstance() : $this->' . $propertyName : ''),
        $upperCasePropertyName,
        $returnType ? $returnType . ' ' : '',
        '$' . $propertyName . ($isSingleObjectRelation ? ' = null' : ''),
        $propertyName,
        '$' . $propertyName,
        $virtualArrayGetter
      );
    }

    $classTemplate = <<< TEMPLATE
declare(strict_types=1);
namespace %s;

use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;

class %s extends %s
{
%s    public function __construct()
    {
        \$this->initializeStorageObjects();
    }

    public function initializeStorageObjects()
    {
%s
    }
}
TEMPLATE;
    $classNameParts = explode('\\', $className);
    $classShortName = array_pop($classNameParts);
    $namespace = implode('\\', $classNameParts);

    $classSourceCode = sprintf(
      $classTemplate,
      $namespace,
      $classShortName,
      '\\' . ltrim($parentClass, '\\'),
      $functionsAndProperties,
      rtrim($objectStorageInitializations)
    );

    $identifier = $identifier ?: sha1($className);
    static::getGeneratedClassCache()->set($identifier, $classSourceCode);
    return $classSourceCode;
  }

  /**
   * @return PhpFrontend
   * @throws NoSuchCacheException
   */
  protected static function getGeneratedClassCache()
  {
    return GeneralUtility::makeInstance(CacheManager::class)->getCache('fourallportal_classes');
  }

  /**
   * @param string $entityClassName
   * @return Module|null
   * @throws ApiException
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function getModuleByHandledEntityClassName($entityClassName)
  {
    foreach ($this->getAllConfiguredModules() as $module) {
      if ($module->getMapper()->getEntityClassName() === $entityClassName) {
        return $module;
      }
    }
    return null;
  }

  /**
   * @return Module[]
   * @throws ApiException
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function getAllConfiguredModules()
  {
    static $configuredModules = [];
    if (!empty($configuredModules)) {
      return $configuredModules;
    }
    $requirePersist = false;

    /** @var Server[] $servers */
    $servers = $this->serverRepository->findBy(['active' => true]);
    foreach ($servers as $server) {
      /** @var Module[] $modules */
      $modules = $server->getModules();
      foreach ($modules as $module) {
        if (empty($module->getModuleName())) {
          $connectorConfiguration = $server->getClient()->getConnectorConfig($module->getConnectorName());
          $module->setModuleName($connectorConfiguration['moduleConfig']['module_name']);
          $module->update();
          $requirePersist = true;
        }
        $configuredModules[$module->getModuleName()] = $module;
      }
    }

    if ($requirePersist) {
      $this->persistenceManager->persistAll();
    }

    return $configuredModules;
  }

  protected function isSkippedField(Module $module, string $fieldName, array $fieldConfiguration, array $validModuleNames = []): bool
  {
    $map = MappingRegister::resolvePropertyMapForMapper($module->getMappingClass());
    if (isset($fieldConfiguration['child']) && !empty($validModuleNames) && !in_array($fieldConfiguration['child'], $validModuleNames)) {
      echo ' - skipped; is a reference to undefined module ' . var_export($fieldConfiguration['child'], true) . PHP_EOL;
      return true;
    } elseif (($map[$fieldName] ?? null) === false) {
      // This property is explicitly mapped in the mapping array, indicating it is manually
      // added to the sub-class of the abstract class we are generating, thus needs to be skipped.
      echo ' - skipped; intentionally marked as ignored in property mapping config' . PHP_EOL;
      return true;
    } elseif (($map[$fieldName] ?? false) !== false) {
      // This property is explicitly mapped in the mapping array, indicating it is manually
      // added to the sub-class of the abstract class we are generating, thus needs to be skipped.
      echo ' - skipped; has custom mapping to ' . var_export($map[$fieldName], true) . PHP_EOL;
      return true;
    } elseif (MappingRegister::resolvePropertyValueSetter($module->getMappingClass(), $fieldName)) {
      // Properties which are mapped using ValueSetter implementations must be skipped.
      echo ' - skipped; has custom value setter' . PHP_EOL;
      return true;
    } elseif (preg_match('/[^a-z0-9_]/i', $fieldName) || preg_match('/[^a-z]/i', $fieldName[0]) && ($map[$fieldName] ?? false) !== false) {
      // Property uses a name that is impossible to express as SQL type and it was NOT defined in
      // the property map for the class. This must yield an exception.
      echo ' - skipped; field name is invalid' . PHP_EOL;
      throw new RuntimeException(
        sprintf(
          'Property "%s" should map to "%s" but the property name contains invalid characters and is ' .
          'not configured in the manual property map. To map this property - which you must do even ' .
          'if the property should just be ignored by mapping it to "false" as target column - please ' .
          'add it to the property map for the model "%s"',
          $fieldName,
          GeneralUtility::underscoredToLowerCamelCase($fieldName),
          $module->getMapper()->getEntityClassName()
        ), 1978775391
      );
    }
    return false;
  }
}
