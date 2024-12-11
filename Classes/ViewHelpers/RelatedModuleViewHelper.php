<?php

namespace Crossmedia\Fourallportal\ViewHelpers;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\ValueReader\ResponseDataFieldValueReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\Exception\PropertyNotAccessibleException;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

class RelatedModuleViewHelper extends AbstractViewHelper
{
  use CompileWithContentArgumentAndRenderStatic;

  protected $escapeOutput = false;

  public function initializeArguments(): void
  {
    $this->registerArgument('content', 'string', 'Tag content - if not specified, taken from tag body');
    $this->registerArgument('module', Module::class, 'Module', true);
    $this->registerArgument('response', 'array', 'Response array', true);
    $this->registerArgument('field', 'string', 'Field name', true);
    $this->registerArgument('verifyRelations', 'boolean', 'Verify related objects are fetchable', true, false);
  }

  public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
  {
    /** @var Module $module */
    $module = $arguments['module'];
    $response = $arguments['response'];
    $field = $arguments['field'];
    if (!array_key_exists($field, (array )($response['result'][0]['properties'] ?? []))) {
      return '<span class="text-danger"><i class="icon fa fa-exclamation"></i> Not in response!</span>';
    }

    // duo to 4allportal version upgrade, relation-field-config can no longer be retrieved via fieldsToLoad property
    if ($module->getConnectorConfiguration()['fieldsToLoad'][$field] == null) {
      return '<span class="text-warning"><i class="icon fa fa-exclamation"></i> Unknown field type</span>';
    }
    $relatedModuleName = static::detectRelatedModule(
      $field,
      $module->getConnectorConfiguration()['fieldsToLoad'][$field],
      $module->getModuleConfiguration()['relation_conf'],
      $module->getModuleName()
    );

    if (!$relatedModuleName) {
      return '<i class="icon fa fa-check"></i> Not a relation';
    }

    $relatedModule = static::getModuleByName($relatedModuleName);
    if (!$relatedModule) {
      return '<span class="text-warning"><i class="icon fa fa-exclamation"></i> Module "' . $relatedModuleName . '" is not supported</span>';
    }

    try {
      // Make an attempt with the FIRST configured dimension mapping enabled. The first entry SHOULD always be the default language.
      $fieldValue = (new ResponseDataFieldValueReader())->readResponseDataField($response['result'][0], $field, $module->getServer()->getDimensionMappings()->current());
      if ($fieldValue != null) {
        $relations = ['result' => (array)$fieldValue];
        if ($arguments['verifyRelations']) {
          $relations = $module->getServer()->getClient()->getBeans($fieldValue, $relatedModule->getConnectorName());
        } else {
          $relations = ['result' => (array)$fieldValue];
        }
      }
    } catch (\RuntimeException $error) {
      $fieldValue = $error->getMessage();
    } catch (PropertyNotAccessibleException $error) {
      $fieldValue = $error->getMessage();
    }

    $variableProvider = $renderingContext->getVariableProvider();
    $variableProvider->add('relations', $relations['result']);
    $variableProvider->add('relatedModule', $relatedModule);
    $variableProvider->add('fieldValueDump', var_export($fieldValue, true));
    $content = $renderChildrenClosure();
    $variableProvider->remove('relations');
    $variableProvider->remove('relatedModule');
    $variableProvider->remove('fieldValueDump');

    return $content;
  }

  protected static function detectRelatedModule($field, array $fieldConfiguration, array $relationConfiguration, $currentModuleName)
  {
    if (!empty($fieldConfiguration['modules'])) {
      return reset($fieldConfiguration['modules']);
    } elseif (!empty($relationConfiguration[$field]['relatedModule'])) {
      return $relationConfiguration[$field]['relatedModule'];
    } elseif (!empty($relationConfiguration[$field]['parent']) && $relationConfiguration[$field]['parent'] !== $currentModuleName) {
      return $relationConfiguration[$field]['parent'];
    } elseif (!empty($relationConfiguration[$field]['child']) && $relationConfiguration[$field]['child'] !== $currentModuleName) {
      return $relationConfiguration[$field]['child'];
    }
  }

  protected static function getModuleByName($moduleName)
  {
    return GeneralUtility::makeInstance(ModuleRepository::class)->findOneBy(['moduleName' => $moduleName]);
  }
}
