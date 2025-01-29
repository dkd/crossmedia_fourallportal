<?php
declare(strict_types=1);

namespace Crossmedia\Fourallportal\ValueReader;

use Crossmedia\Fourallportal\Domain\Model\DimensionMapping;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Extbase\Reflection\Exception\PropertyNotAccessibleException;

class ResponseDataFieldValueReader implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function readResponseDataField(array $result, string $fieldName, DimensionMapping $dimensionMapping = null)
    {
        if (is_array($result['properties'][$fieldName] ?? false)
            && array_key_exists('value', $result['properties'][$fieldName][0] ?? [])
            && is_array($result['properties'][$fieldName][0]['dimensions'] ?? false)
        ) {
            if ($dimensionMapping !== null) {
                if (empty($result['properties'][$fieldName][0]['dimensions'])) {
                    // This is a dimension capable response, but the dimensions are empty; return the value since it applies to
                    // every possible translation.
                    return $result['properties'][$fieldName][0]['value'];
                }

                // Look for a value in dimensions specific to this dimension mapping.
                foreach ($result['properties'][$fieldName] as $dimensionObject) {
                    if ($dimensionMapping->matches($dimensionObject['dimensions'])) {
                        return $dimensionObject['value'];
                    }
                }

                $errorData = [];
                foreach ($dimensionMapping->getDimensions() as $dimension) {
                    $errorData[$dimension->getName()] = $dimension->getValue();
                }

                throw new PropertyNotAccessibleException(
                    'Cannot read property "' . $fieldName . '" from PIM response. ' .
                    sprintf(
                        'Dimension mapping is in effect but data set has no dimensioned data which matches %s.',
                        json_encode($errorData)
                    ),
                    1527168392
                );
            }

            $this->logger?->warning('Missing property "' . $fieldName . '" in data: ' . json_encode($result['properties'] ?? []));
            throw new PropertyNotAccessibleException(
                'Cannot read property "' . $fieldName . '" from PIM response. Dimension mapping is NOT in effect but property contains dimensions.',
                1527168391
            );
        } elseif (is_array($result['properties'][$fieldName][0] ?? false)
            && isset($result['properties'][$fieldName][0]['dimensions'])
        ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Property "%s" is configured with dimensions, but the first dimension locale "%s" has no "value" ' .
                    'attribute. This issue has to be addressed on the PIM system by assigning a value to the property.',
                    $fieldName,
                    $result['properties'][$fieldName][0]['dimensions']['locale']
                ), 5482380929
            );
        }
        return $result['properties'][$fieldName]['value'] ?? $result['properties'][$fieldName] ?? null;
    }
}
