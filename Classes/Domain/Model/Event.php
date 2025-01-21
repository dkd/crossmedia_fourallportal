<?php

namespace Crossmedia\Fourallportal\Domain\Model;

use Crossmedia\Fourallportal\Utility\ConstantsUtility;
use Doctrine\DBAL\Exception;
use PDO;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

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
 * Events
 */
class Event extends AbstractEntity
{
  protected int $eventId = 0;
  protected string $eventType = '';
  protected string $status = 'pending';
  protected int $skipUntil = 0;
  protected int $nextRetry = 0;
  protected int $retries = 0;
  protected string $objectId = '';
  protected ?Module $module = null;
  protected string $headers = '';
  protected string $response = '';
  protected string $url;
  protected string $payload = '';
  protected string $message;
  protected int $crdate;
  protected int $tstamp;
  protected bool $processing = false;
  protected array $beanData = [];

  public function getBeanData(): array
  {
    return $this->beanData;
  }

  public function setBeanData(array $beanData): void
  {
    $this->beanData = $beanData;
  }

  public function getEventId(): int
  {
    return $this->eventId;
  }

  public function setEventId(int $eventId): void
  {
    $this->eventId = $eventId;
  }

  public function getStatus(): string
  {
    return $this->status;
  }

  public function setStatus(string $status): void
  {
    $this->status = $status;
  }

  public function getSkipUntil(): int
  {
    return $this->skipUntil;
  }

  public function getRetries(): int
  {
    return $this->retries;
  }

  public function setRetries(int $retries): void
  {
    $this->retries = $retries;
  }

  public function getNextRetry(): int
  {
    return $this->nextRetry;
  }

  public function setNextRetry(int $nextRetry): void
  {
    $this->nextRetry = $nextRetry;
  }

  public function setSkipUntil(int $skipUntil): void
  {
    $this->skipUntil = $skipUntil;
  }

  public function getObjectId(): string
  {
    return $this->objectId;
  }

  public function setObjectId(string $objectId): void
  {
    $this->objectId = $objectId;
  }

  public function getModule(): ?Module
  {
    return $this->module;
  }

  public function setModule(Module $module): void
  {
    $this->module = $module;
  }

  public function getEventType(): string
  {
    return $this->eventType;
  }

  public function setEventType(string $eventType): void
  {
    $this->eventType = $eventType;
  }

  public function getHeaders(): string
  {
    return $this->headers;
  }

  public function setHeaders(string $headers): void
  {
    $this->headers = $headers;
  }

  public function getResponse(): string
  {
    return $this->response;
  }

  public function setResponse(string $response): void
  {
    $this->response = $response;
  }

  public function getUrl(): string
  {
    return $this->url;
  }


  public function setUrl(string $url): void
  {
    $this->url = $url;
  }

  public function getPayload(): string
  {
    return $this->payload;
  }

  public function setPayload(string $payload): void
  {
    $this->payload = $payload;
  }

  public function getMessage(): string
  {
    return $this->message;
  }

  public function setMessage(string $message): void
  {
    $this->message = $message;
  }

  public function getCrdate(): int
  {
    return $this->crdate;
  }

  public function setCrdate(int $crdate): void
  {
    $this->crdate = $crdate;
  }

  public function getTstamp(): int
  {
    return $this->tstamp;
  }

  public function setTstamp(int $tstamp): void
  {
    $this->tstamp = $tstamp;
  }

  /**
   * @throws Exception
   */
  public function isProcessing(): bool
  {
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_fourallportal_domain_model_event');
    $query = $queryBuilder->select('processing')
      ->from('tx_fourallportal_domain_model_event')
      ->where($queryBuilder->expr()->eq('uid', $this->uid))
      ->setMaxResults(1);
    return $this->processing = (bool)$query->executeQuery()->fetchFirstColumn()[0];
  }

  public function setProcessing(bool $processing): void
  {
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_fourallportal_domain_model_event');
    $query = $queryBuilder->update('tx_fourallportal_domain_model_event')
      ->set('processing', $processing, PDO::PARAM_INT)
      ->where($queryBuilder->expr()->eq('uid', $this->uid));
    $query->executeStatement();
    $this->processing = $processing;
  }

  public static function resolveEventType(int $eventTypeId): string
  {
    $map = [
      0 => 'delete',
      1 => 'update',
      2 => 'create'
    ];
    return $map[$eventTypeId];
  }

  public function getMostRecentObjectLog(): string
  {
    return implode(PHP_EOL, array_reverse(explode(PHP_EOL, shell_exec('tail -n 1000 ' . $this->getObjectLogFilePath()))));
  }

  public function getMostRecentEventLog(): string
  {
    return implode(PHP_EOL, array_reverse(explode(PHP_EOL, shell_exec('tail -n 1000 ' . $this->getEventLogFilePath()))));
  }

  public function getObjectLogFilePath(): string
  {
    return sprintf(
      implode(
        DIRECTORY_SEPARATOR,
        [
          Environment::getVarPath(),
          'log',
          ConstantsUtility::LOG_BASEDIR,
          'objects',
          '%s',
          '%s.log',
        ]
      ),
      $this->getModule()->getModuleName(),
      $this->getObjectId()
    );
  }

  public function getEventLogFilePath(): string
  {
    return sprintf(
      implode(
        DIRECTORY_SEPARATOR,
        [
          Environment::getVarPath(),
          'log',
          ConstantsUtility::LOG_BASEDIR,
          'events',
          '%s',
          '%s.log',
        ]
      ),
      $this->getModule()->getModuleName(),
      $this->getObjectId()
    );
  }
}
