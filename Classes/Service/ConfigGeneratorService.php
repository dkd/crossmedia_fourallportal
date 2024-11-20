<?php

namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator;
use Crossmedia\Fourallportal\DynamicModel\DynamicModelRegister;
use Crossmedia\Fourallportal\Error\ApiException;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

class ConfigGeneratorService
{

  public function __construct(
    protected ?DataMapper            $dataMapper = null,
    protected ?DynamicModelGenerator $dynamicModelGenerator = null
  )
  {
  }

  /**
   * Generate TCA for model
   *
   * This command can be used instead or together with the
   * dynamic model feature to generate a TCA file for a particular
   * entity, by its class name.
   *
   * Internally the class name is analysed to determine the
   * extension it belongs to, and makes an assumption about the
   * table name. The command then writes the generated TCA to the
   * exact TCA configuration file (by filename convention) and
   * will overwrite any existing TCA in that file.
   *
   * Should you need to adapt individual properties such as the
   * field used for label, the icon path etc. please use the
   * Configuration/TCA/Overrides/$tableName.php file instead.
   *
   * @param null $entityClassName
   * @param bool $readOnly If TRUE, generates TCA fields as read-only
   * @throws Exception
   */
  public function generateTableConfiguration($entityClassName = null, $readOnly = false): void
  {
    foreach ($this->getEntityClassNames($entityClassName) as $entityClassName) {
      $tca = $this->dynamicModelGenerator->generateAutomaticTableConfigurationForModelClassName($entityClassName, $readOnly);
      $table = $this->dataMapper->getDataMap($entityClassName)->getTableName();
      $extensionKey = $this->getExtensionKeyFromEntityClasName($entityClassName);

      // Note: although extPath() supports a second argument we concatenate to prevent file exists. It may not exist yet!
      $targetFilePathAndFilename = ExtensionManagementUtility::extPath($extensionKey) . 'Configuration/TCA/' . $table . '.php';
      $targetFileContent = '<?php' . PHP_EOL . 'return ' . var_export($tca, true) . ';' . PHP_EOL;
      GeneralUtility::writeFile(
        $targetFilePathAndFilename,
        $targetFileContent
      );
    }
  }

  /**
   * Generate abstract entity class
   *
   * This command can be used as substitute for the automatic
   * model class generation feature. Each entity class generated
   * with this command prevents usage of the dynamically created
   * class (which still gets created!). To re-enable dynamic
   * operation simply remove the generated abstract class again.
   *
   * Generates an abstract PHP class in the same namespace as
   * the input entity class name. The abstract class contains
   * all the dynamically generated properties associated with
   * the Module.
   *
   * @param SymfonyStyle $io
   * @param null $entityClassName
   * @param bool $strict If TRUE, generates strict PHP code
   * @throws ApiException
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function generateAbstractModelClassCommand(SymfonyStyle $io, $entityClassName = null, $strict = false): void
  {
    $modulesByEntityClassName = [];
    foreach ($this->dynamicModelGenerator->getAllConfiguredModules() as $module) {
      if ($module->isEnableDynamicModel()) {
        $modulesByEntityClassName[$module->getMapper()->getEntityClassName()] = $module;
      }
    }

    foreach ($this->getEntityClassNames($entityClassName) as $entityClassName) {
      if (!isset($modulesByEntityClassName[$entityClassName])) {
        $io->writeln('Cannot generate model for ' . $entityClassName . ' - has no configured module to handle the entity' . PHP_EOL);
        continue;
      }
      $extensionKey = $this->getExtensionKeyFromEntityClasName($entityClassName);
      $module = $modulesByEntityClassName[$entityClassName];
      $sourceCode = $this->dynamicModelGenerator->generateAbstractModelForModule($module, $strict);
      $abstractClassName = 'Abstract' . substr($entityClassName, strrpos($entityClassName, '\\') + 1);
      $targetFileContent = '<?php' . PHP_EOL . $sourceCode . PHP_EOL;
      $targetFilePathAndFilename = ExtensionManagementUtility::extPath($extensionKey) . 'Classes/Domain/Model/' . $abstractClassName . '.php';
      GeneralUtility::writeFile(
        $targetFilePathAndFilename,
        $targetFileContent
      );
    }
  }

  /**
   * Generate additional SQL schema file
   *
   * This command can be used as substitute for the automatic
   * SQL schema generation - using it disables the analysis of
   * the Module to read schema properties. If used, should be
   * combined with both of the other "generate" commands from
   * this package, to create a completely static set of assets
   * based on the configured Modules and prevent dynamic changes.
   *
   * Generates all schemas for all modules, and generates a static
   * SQL schema file in the extension to which the entity belongs.
   * The SQL schema registration hook then circumvents the normal
   * schema fetching and uses the static schema instead, when the
   * extension has a static schema.
   */
  public function generateSqlSchemaCommand(): void
  {
    $modulesByExtensionKey = [];
    foreach ($this->dynamicModelGenerator->getAllConfiguredModules() as $name => $module) {
      if (!$module->isEnableDynamicModel()) {
        continue;
      }
      $extensionKey = $this->getExtensionKeyFromEntityClasName($module->getMapper()->getEntityClassName());
      if (!isset($modulesByExtensionKey[$extensionKey])) {
        $modulesByExtensionKey[$extensionKey] = [$module];
      } else {
        $modulesByExtensionKey[$extensionKey][] = $module;
      }
    }
    foreach ($modulesByExtensionKey as $extensionKey => $groupedModules) {
      $targetFileContent = implode(PHP_EOL, $this->dynamicModelGenerator->generateSchemasForModules($groupedModules));
      $targetFilePathAndFilename = ExtensionManagementUtility::extPath($extensionKey) . 'Configuration/SQL/DynamicSchema.sql';
      GeneralUtility::mkdir_deep(dirname($targetFilePathAndFilename));
      GeneralUtility::writeFile(
        $targetFilePathAndFilename,
        $targetFileContent
      );
    }
  }

  /**
   * @param string $entityClassName
   * @return array
   */
  protected function getEntityClassNames($entityClassName)
  {
    if ($entityClassName) {
      $entityClassNames = [$entityClassName];
    } else {
      $entityClassNames = $this->dynamicModelGenerator->getModelClassNamesRegisteredForAutomaticHandling();
    }
    return $entityClassNames;
  }

  /**
   * @param string $entityClassName
   * @return string
   */
  protected function getExtensionKeyFromEntityClasName($entityClassName)
  {
    $entityClassNameParts = explode('\\', $entityClassName);
    $entityClassNameBase = array_slice($entityClassNameParts, 0, -3);
    $extensionName = array_pop($entityClassNameBase);
    return GeneralUtility::camelCaseToLowerCaseUnderscored($extensionName);
  }
}