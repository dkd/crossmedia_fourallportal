<?php

declare(strict_types=1);

namespace Crossmedia\Fourallportal\EventListener;

use Crossmedia\Fourallportal\Utility\DynamicModelUtility;
use TYPO3\CMS\Core\Database\Event\AlterTableDefinitionStatementsEvent;

final class TablesDefinitionListener
{
  public function __invoke(AlterTableDefinitionStatementsEvent $event): void
  {
    $mergedData = DynamicModelUtility::addSchemasForAllModules($event->getSqlData());
    $event->setSqlData($mergedData);
  }
}
