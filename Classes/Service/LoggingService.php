<?php

namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\LogEntry;
use Crossmedia\Fourallportal\Utility\ConstantsUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LoggingService implements SingletonInterface
{
  public function logFileTransferActivity(string $url, string $localFileName, int $severity = 1/** GeneralUtility::SYSLOG_SEVERITY_INFO */): void
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_FILE);
    $this->writeEntry($logFile, $url . ' ' . $localFileName, $severity);
  }

  /**
   * @param int $numberOfEntries
   * @return iterable
   */
  public function getFileTransferActivity(int $numberOfEntries = 0): iterable
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_FILE);
    return $this->getEntries($logFile, $numberOfEntries);
  }

  public function logConnectionActivity(string $message, int $severity = 1/** GeneralUtility::SYSLOG_SEVERITY_INFO */): void
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_CONNECTION);
    $this->writeEntry($logFile, $message, $severity);
  }

  /**
   * @param int $numberOfEntries
   * @return iterable
   */
  public function getConnectionActivity(int $numberOfEntries): iterable
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_CONNECTION);
    return $this->getEntries($logFile, $numberOfEntries);
  }

  public function logEventActivity(Event $event, string $message, int $severity = 1/** GeneralUtility::SYSLOG_SEVERITY_INFO */): void
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_EVENT, $event->getEventId());
    $this->writeEntry($logFile, $message, $severity);
  }

  /**
   * @param Event $event
   * @param int $numberOfEntries
   * @return iterable
   */
  public function getEventActivity(Event $event, int $numberOfEntries = 0): iterable
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_EVENT, $event->getEventId());
    return $this->getEntries($logFile, $numberOfEntries);
  }

  public function logObjectActivity(string $uuid, string $message, string $property, int $severity = 1/** GeneralUtility::SYSLOG_SEVERITY_INFO */): void
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_OBJECT, $uuid);
    $this->writeEntry($logFile, $property . ' ' . $message, $severity);
  }

  /**
   * @param string $uuid
   * @param int $numberOfEntries
   * @return iterable
   */
  public function getObjectActivity(string $uuid, int $numberOfEntries = 0): iterable
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_OBJECT, $uuid);
    return $this->getEntries($logFile, $numberOfEntries);
  }

  public function logSchemaActivity(string $message, int $severity = 1/** GeneralUtility::SYSLOG_SEVERITY_INFO */): void
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_SCHEMA);
    $this->writeEntry($logFile, $message, $severity);
  }

  /**
   * @param int $numberOfEntries
   * @return iterable
   */
  public function getSchemaActivity(int $numberOfEntries = 0): iterable
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_SCHEMA);
    return $this->getEntries($logFile, $numberOfEntries);
  }

  /**
   * @param int $numberOfEntries
   * @return iterable
   */
  public function getErrorActivity(int $numberOfEntries = 0): iterable
  {
    $logFile = $this->resolveLogFilePath(ConstantsUtility::TEXT_ERRORS);
    return $this->getEntries($logFile, $numberOfEntries);
  }

  /**
   * @param string $logFile
   * @param int $numberOfEntries
   * @return iterable
   */
  protected function getEntries(string $logFile, int $numberOfEntries): iterable
  {
    if (!file_exists($logFile)) {
      return [];
    }
    if (!$numberOfEntries) {
      $contents = file_get_contents($logFile);
    } else {
      $contents = shell_exec('tail -n ' . $numberOfEntries . ' ' . $logFile);
    }
    $entries = explode(PHP_EOL, trim($contents));
    $items = [];
    foreach (array_reverse($entries) as $entry) {
      [$date, $severity, $message] = explode(' ', $entry, 3);
      $items[] = GeneralUtility::makeInstance(LogEntry::class, $date, (int)$severity, (string)$message);
    }
    return $items;
  }

  protected function writeEntry(string $logFile, string $message, int $severity): void
  {
    if (empty($message)) {
      // Cowardly refusing to create an empty log message
      return;
    }
    $fp = fopen($logFile, 'a+');
    // FIXME: $fp should not return boolean !!
    if ($fp !== false) {
      fwrite($fp, date('Y-m-d_H:i:s') . ' ' . $severity . ' ' . $message . PHP_EOL);
      fclose($fp);
      if ($severity >= 2/** GeneralUtility::SYSLOG_SEVERITY_WARNING */) {
        $fp = fopen($this->resolveLogFilePath(ConstantsUtility::TEXT_ERRORS), 'a+');
        fwrite($fp, date('Y-m-d_H:i:s') . ' ' . $severity . ' ' . $message . PHP_EOL);
        fclose($fp);
      }
    }
  }

  protected function resolveLogFilePath(string $type, string $identity = null): string
  {

    $fullPath = Environment::getProjectPath() . "/" .ConstantsUtility::LOG_BASEDIR;

    # Create extension log file directory
    if (isset($identity)) {
      $fullPath .=  $type;
    }
    if (!is_dir($fullPath)) {
      GeneralUtility::mkdir_deep($fullPath);
    }
    if (isset($identity)) {
      $fullPath .=  '/' . $identity . '.log';
    } else {
      $fullPath .= $type . '.log';
    }

    if (!is_file($fullPath)) {
      touch($fullPath);
    }

    return GeneralUtility::getFileAbsFileName($fullPath);
  }
}
