<?php

namespace Crossmedia\Fourallportal\Domain\Model;

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

use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Mapping\MappingInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

/**
 * Module
 */
class Module extends AbstractEntity
{
  protected string $connectorName = '';
  protected string $moduleName = '';
  protected string $mappingClass = '';
  protected string $configHash = '';
  protected int $lastEventId = 0;
  protected int $lastReceivedEventId = 0;
  protected string $shellPath = '';
  protected int $storagePid = 0;
  protected int $falStorage = 0;
  protected string $usageFlag = '';
  protected string $testObjectUuid = '';
  protected bool $enableDynamicModel = true;
  protected ?Server $server = null;
  protected bool $containsDimensions = true;

  public function getConnectorName(): string
  {
    return $this->connectorName;
  }

  public function setConnectorName(string $connectorName): void
  {
    $this->connectorName = $connectorName;
  }

  public function getModuleName(): string
  {
    return $this->moduleName;
  }

  public function setModuleName(string $moduleName): void
  {
    $this->moduleName = $moduleName;
  }

  public function getConfigHash(): string
  {
    return $this->configHash;
  }

  public function setConfigHash(string $configHash): void
  {
    $this->configHash = $configHash;
  }

  public function getLastEventId(): int
  {
    return $this->lastEventId;
  }

  public function setLastEventId(int $lastEventId): void
  {
    $this->lastEventId = $lastEventId;
  }

  public function getLastReceivedEventId(): int
  {
    return $this->lastReceivedEventId;
  }

  public function setLastReceivedEventId(int $lastReceivedEventId): void
  {
    $this->lastReceivedEventId = $lastReceivedEventId;
  }

  public function getServer(): ?Server
  {
    return $this->server;
  }

  public function setServer(Server $server): void
  {
    $this->server = $server;
  }

  public function getShellPath(): string
  {
    return $this->shellPath;
  }

  public function setShellPath(string $shellPath): void
  {
    $this->shellPath = $shellPath;
  }

  public function getStoragePid(): int
  {
    return $this->storagePid;
  }

  public function setStoragePid(int $storagePid): void
  {
    $this->storagePid = $storagePid;
  }

  public function getFalStorage(): int
  {
    return $this->falStorage;
  }

  public function setFalStorage(int $falStorage): void
  {
    $this->falStorage = $falStorage;
  }

  public function getUsageFlag(): string
  {
    return $this->usageFlag;
  }

  public function setUsageFlag(string $usageFlag): void
  {
    $this->usageFlag = $usageFlag;
  }

  public function getMapper(): MappingInterface
  {
    return GeneralUtility::makeInstance($this->getMappingClass());
  }

  public function getMappingClass(): string
  {
    return $this->mappingClass;
  }

  public function setMappingClass(string $mappingClass): void
  {
    $this->mappingClass = $mappingClass;
  }

  public function getTestObjectUuid(): string
  {
    return $this->testObjectUuid;
  }

  public function setTestObjectUuid(string $testObjectUuid): void
  {
    $this->testObjectUuid = $testObjectUuid;
  }

  public function isEnableDynamicModel(): bool
  {
    return $this->enableDynamicModel;
  }

  public function setEnableDynamicModel(bool $enableDynamicModel): void
  {
    $this->enableDynamicModel = $enableDynamicModel;
  }

  public function isContainsDimensions(): bool
  {
    return $this->containsDimensions;
  }

  public function getContainsDimensions(): bool
  {
    return $this->containsDimensions;
  }

  public function setContainsDimensions(bool $containsDimensions): void
  {
    $this->containsDimensions = (bool)$containsDimensions;
  }

  /**
   * @return array
   * @throws ApiException
   */
  public function getModuleConfiguration(): array
  {
//    static $configs = [];
//    if (!array_key_exists($this->moduleName, $configs)) {
//      $configs[$this->moduleName] = $this->getServer()->getClient()->getModuleConfig($this->moduleName);
//    }
//    return $configs[$this->moduleName];
    return $this->getServer()->getClient()->getModuleConfig($this->moduleName);
  }

  /**
   * @return array
   * @throws ApiException
   */
  public function getConnectorConfiguration(): array
  {
//    static $configs = [];
//    if (!array_key_exists($this->connectorName, $configs)) {
//      $configs[$this->connectorName] = $this->getServer()->getClient()->getConnectorConfig($this->connectorName);
//    }
//    return $configs[$this->connectorName];
    return $this->getServer()->getClient()->getConnectorConfig($this->connectorName);
  }

  /**
   * @return bool
   * @throws ApiException
   */
  public function verifySchemaVersion(): bool
  {
    return $this->configHash === $this->getConnectorConfiguration()['config_hash'];
  }

  /**
   * @return void
   * @throws ApiException
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function pinSchemaVersion(): void
  {
    $this->configHash = $this->getConnectorConfiguration()['config_hash'];
    $this->update();
  }

  /**
   * @throws UnknownObjectException
   * @throws IllegalObjectTypeException
   */
  public function update(): void
  {
    GeneralUtility::makeInstance(ModuleRepository::class)->update($this);
  }
}
