<?php

namespace Crossmedia\Fourallportal\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

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
 * Module
 */
class DimensionMapping extends AbstractEntity
{
  protected string|int $language = '';
  protected ?Server $server = null;

  /**
   * modules
   *
   * @var ObjectStorage<Dimension>|null
   */
  #[\TYPO3\CMS\Extbase\Annotation\ORM\Cascade(['value' => 'remove'])]
  protected ?ObjectStorage $dimensions = null;
  protected string $metricOrImperial = 'Metric';
  protected bool $active = false;

  public function getLanguage(): int
  {
    return $this->language;
  }

  public function setLanguage(int $language): void
  {
    $this->language = $language;
  }

  public function getServer(): ?Server
  {
    return $this->server;
  }

  public function setServer(Server $server): void
  {
    $this->server = $server;
  }

  public function getDimensions(): ?ObjectStorage
  {
    return $this->dimensions;
  }

  public function setDimensions(ObjectStorage $dimensions): void
  {
    $this->dimensions = $dimensions;
  }

  public function getMetricOrImperial(): string
  {
    return $this->metricOrImperial;
  }

  public function setMetricOrImperial(string $metricOrImperial): void
  {
    $this->metricOrImperial = $metricOrImperial;
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
   * Returns $this if the current object is the default dimension;
   * or behaves as an emulated 1:1 relation to a default language.
   *
   * @return DimensionMapping | null
   */
  public function getDefaultDimensionMapping(): DimensionMapping|null
  {
    if ($this->language === 0 || $this->server === null) {
      return $this;
    }

    foreach ($this->server->getDimensionMappings() as $dimensionMapping) {
      if ($dimensionMapping->getLanguage() === 0) {
        return $dimensionMapping;
      }
    }
    // TODO: throw a deferral if the resolved value is null
    return null;
  }

  public function matches($dimensions): bool
  {
    if ($this->dimensions === null || $dimensions === null) {
      return false;
    }
    foreach ($this->dimensions as $dimension) {
      if (!isset($dimensions[$dimension->getName()])) {
        // We will allow and ignore the case of a requested locale not being present in the PIM data.
        // Technically this constitutes an error. Thus we throw this little member-berry in here:
        // TODO: throw an exception once the data on PIM is consistent, to report such a problem as an error.
        continue;
      }
      if ($dimensions[$dimension->getName()] != $dimension->getValue()) {
        return false;
      }
    }
    return true;
  }
}
