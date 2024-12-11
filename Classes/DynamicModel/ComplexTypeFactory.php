<?php
namespace Crossmedia\Fourallportal\DynamicModel;

use Crossmedia\Fourallportal\Domain\Model\ComplexType;

/**
 * Class ComplexTypeFactory
 *
 * Handles integration with complex/compound data types
 * from the MAM API. A ComplexType is a special field
 * type which on the TYPO3 side is expressed as a 1:1
 * relation to a secondary database row and object
 * which contains multiple properties along with the
 * actual value that the field contains.
 */
class ComplexTypeFactory
{
    /**
     * Contains templates stored in two dimensions:
     *
     * [$instanceOfModel, array $propertiesToMatch]
     *
     * Where $propertiesToMatch is an array that must
     * exist in the remote API field configuration for
     * the $instaceOfModel to be considered a match.
     *
     * @var array
     */
    protected static $templates = [];

    /**
     * Gets an instance of the ComplexType domain model and
     * pre-configures it with properties as required by the
     * sub match properties.
     *
     * Takes a "type" (corresponds to the field type returned
     * from the MAM API, for example "CEMetric") which must
     * be pre-configured, and a sub-match which defines a set
     * of properties that must be matched as well, or the
     * ComplexType is reported as missing.
     *
     * ComplexType templates are created from `ext_localconf.php`
     * or other init runtime scripts, by calling:
     *
     *     ComplexTypeFactory::registerComplexType($instance)
     *
     * Which receives an instance of ComplexType that you
     * manually prepared with the properties you wish to be
     * available to the system.
     *
     * @param string $type
     * @param array $subMatch
     * @return ComplexType
     * @throws \RuntimeException
     */
    public static function getPreparedComplexType($type, array $subMatch = array())
    {
        $complexType = new ComplexType();
        $complexType->setType($type);
        $complexType->setFieldName($subMatch['name']);
        $complexType->setLabel($subMatch['metric']['defaultUnit']);
        $complexType->setName($subMatch['metric']['name']);
        return $complexType;
    }

    /**
     * @param string $type
     * @param string $name
     * @param string $label
     * @param string $fieldName
     * @param array $match
     * @return void
     */
    public static function createComplexTypeTemplate($type, $name, $label, $fieldName, array $match): void
    {
        $complexType = new ComplexType();
        $complexType->setType($type);
        $complexType->setName($name);
        $complexType->setLabel($label);
        $complexType->setFieldName($fieldName);
        static::registerComplexTypeTemplate($complexType, $match);
    }

    /**
     * Registers a ComplexType instance (which you manually
     * configured in init runtime) to be available to the
     * system. Although the ComplexType instance you pass is
     * technically a domain object, it is not a persisted
     * object. When the ComplexType is requested, a fresh
     * clone is returned so it can be directly attached to
     * model instances and persisted to database.
     *
     * @param ComplexType $complexType
     * @param array $subMatch
     * @return void
     */
    public static function registerComplexTypeTemplate(ComplexType $complexType, array $subMatch): void
    {
        static::$templates[] = [$complexType, $subMatch];
    }
}
