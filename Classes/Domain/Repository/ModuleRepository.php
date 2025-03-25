<?php

namespace Crossmedia\Fourallportal\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;

/***
 *
 * This file is part of the "4AllPortal Connector" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Marc Neuhaus <marc@mia3.com>, MIA3 GmbH & Co. KG
 *
 ***/

/**
 * The repository for Modules
 */
class ModuleRepository extends Repository
{
  public function findOneByMappingClass(string $mappingClass)
  {
    static $modulesByMappingClass = [];
    if (empty($modulesByMappingClass)) {
      $query = $this->createQuery();
      $query->equals('server.active', 1);
      foreach ($query->execute() as $module) {
        $modulesByMappingClass[$module->getMappingClass()] = $module;
      }
    }
    return $modulesByMappingClass[$mappingClass] ?? null;
  }
}
