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

use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ApiClient;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Server
 */
class Server extends AbstractEntity
{
  protected string $domain = '';
  protected string $customerName = '';
  protected string $username = '';
  protected string $password = '';
  protected bool $active = false;

  /**
   * modules
   *
   * @var ObjectStorage<Module>|null
   */
  #[\TYPO3\CMS\Extbase\Annotation\ORM\Cascade(['value' => 'remove'])]
  protected ?ObjectStorage $modules;

  /**
   * modules
   *
   * @var ObjectStorage<DimensionMapping>|null
   */
  #[\TYPO3\CMS\Extbase\Annotation\ORM\Cascade(['value' => 'remove'])]
  protected ?ObjectStorage $dimensionMappings;

  /**
   * __construct
   */
  public function __construct()
  {
    //Do not remove the next line: It would break the functionality
    $this->initStorageObjects();
  }

  /**
   * Initializes all ObjectStorage properties
   * Do not modify this method!
   * It will be rewritten on each save in the extension builder
   * You may modify the constructor of this class instead
   *
   * @return void
   */
  protected function initStorageObjects(): void
  {
    $this->modules = new ObjectStorage();
    $this->dimensionMappings = new ObjectStorage();
  }

  public function getDomain(): string
  {
    return $this->domain;
  }

  public function setDomain(string $domain): void
  {
    $this->domain = $domain;
  }

  public function getCustomerName(): string
  {
    return $this->customerName;
  }

  public function setCustomerName(string $customerName): void
  {
    $this->customerName = $customerName;
  }

  public function getUsername(): string
  {
    return $this->username;
  }

  public function setUsername(string $username): void
  {
    $this->username = $username;
  }

  public function getPassword(): string
  {
    return $this->password;
  }

  public function setPassword(string $password): void
  {
    $this->password = $password;
  }

  public function addModule(Module $module): void
  {
    $this->modules->attach($module);
  }

  public function removeModule(Module $moduleToRemove): void
  {
    $this->modules->detach($moduleToRemove);
  }

  /**
   * Returns the modules
   *
   * @return ObjectStorage<Module> modules
   */
  public function getModules(): ?ObjectStorage
  {
    return $this->modules;
  }

  /**
   * Sets the modules
   *
   * @param ObjectStorage<Module> $modules
   * @return void
   */
  public function setModules(ObjectStorage $modules): void
  {
    $this->modules = $modules;
  }

  public function getModule(string $moduleName): ?Module
  {
    foreach ($this->getModules() as $module) {
      if ($module->getModuleName() === $moduleName) {
        return $module;
      }
    }
    return null;
  }

  /**
   * return the url in the following format: http://[DOMAIN]/[CUSTOMER_NAME]/dataservice
   * @return string
   */
  public function getDataUrl(): string
  {
    // TODO: change to new object image URL /api/modules/[MODULE]/objects/[OBJECT_ID]/media/small
    return sprintf(
      '%s/service/object_image/get',
      rtrim($this->getDomain(), '/')#,
    );
  }

  /**
   * return the url in the following format: http://[DOMAIN]/service/usermanagement/login
   * @return string
   */
  public function getLoginUrl(): string
  {
    return sprintf(
      '%s/service/usermanagement/login',
      rtrim($this->getDomain(), '/')
    );
  }

  public function getApiUrl(): string
  {
    return sprintf(
      '%s/api/',
      rtrim($this->getDomain(), '/')
    );
  }

  /**
   *  return the url in the following format: http://[DOMAIN]/rest/PAPRemoteService
   * @return string
   */
  public function getRestUrl(): string
  {
    return sprintf(
      '%s/rest/',
      rtrim($this->getDomain(), '/')
    );
  }

  public function getActive(): bool
  {
    return $this->active;
  }

  public function setActive(bool $active): void
  {
    $this->active = $active;
  }

  public function isActive(): bool
  {
    return $this->active;
  }

  /**
   * @return ApiClient
   * @throws ApiException
   */
  public function getClient()
  {
    static $client = null;
    if ($client) {
      return $client;
    }
    $client = GeneralUtility::makeInstance(ApiClient::class, $this);
    $client->login();
    return $client;
  }

  public function getDimensionMappings(): ?ObjectStorage
  {
    return $this->dimensionMappings;
  }

  /**
   * @param ObjectStorage $dimensionMappings
   */
  public function setDimensionMappings(ObjectStorage $dimensionMappings): void
  {
    $this->dimensionMappings = $dimensionMappings;
  }

}
