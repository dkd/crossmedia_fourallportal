<?php
namespace Crossmedia\Fourallportal\DynamicModel;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class DynamicModelRegister
{
    /**
     * @var array
     */
    protected static $handledModelClasses = [];

    /**
     * @var array
     */
    protected static $overriddenSqlTypes = [];

    /**
     * @var array
     */
    protected static $lazyProperties = [];

    /**
     * @param string $modelClassName
     */
    public static function registerModelForAutomaticHandling(string $modelClassName): void
    {
        if (!in_array($modelClassName, static::$handledModelClasses)) {
            $segments = explode('\\', $modelClassName);
            $className = array_pop($segments);
            $segments[] = 'Abstract' . $className;
            $abstractClassName = implode('\\', $segments);
            if (!class_exists($abstractClassName)) {
                class_alias(AbstractEntity::class, $abstractClassName);
            }
            static::$handledModelClasses[] = $modelClassName;
        }
    }

    /**
     * @return array
     */
    public static function getModelClassNamesRegisteredForAutomaticHandling(): array
    {
        return static::$handledModelClasses;
    }

    /**
     * @param string $modelClassName
     * @return bool
     */
    public static function isModelRegisteredForAutomaticHandling(string $modelClassName): bool
    {
        return in_array($modelClassName, static::$handledModelClasses);
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $type
     */
    public static function overrideSqlType($table, $column, $type): void
    {
        static::$overriddenSqlTypes[$table][$column] = $type;
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $originalType
     * @return mixed
     */
    public static function getOverriddenOrOriginalSqlType(string $table, string $column, string $originalType)
    {
        return static::$overriddenSqlTypes[$table][$column] ?? $originalType;
    }

    /**
     * @param string $modelClassName
     * @param string|null $propertyName
     */
    public static function registerLazyModelProperty(string $modelClassName, string|null $propertyName = null): void
    {
        $propertyName = $propertyName ?? '_all';
        static::$lazyProperties[$modelClassName][$propertyName] = $propertyName;
    }

    /**
     * @param string $modelClassName
     * @param string|null $propertyName
     * @return bool
     */
    public static function isLazyProperty(string $modelClassName, string|null$propertyName = null): bool
    {
        $propertyName = $propertyName ?? '_all';
        return isset(static::$lazyProperties[$modelClassName][$propertyName]) || isset(static::$lazyProperties[$modelClassName]['_all']);
    }
}
