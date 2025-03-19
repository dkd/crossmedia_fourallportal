<?php

declare(strict_types=1);

namespace Crossmedia\Fourallportal\Utility;

use Crossmedia\Fourallportal\DynamicModel\DynamicModelRegister;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DynamicModelUtility
{
    /**
     * @param array $sqlString
     * @return array
     */
    public static function addSchemasForAllModules(array $sqlString): array
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
}
