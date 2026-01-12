<?php

namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\LogEntry;
use Crossmedia\Fourallportal\Utility\ConstantsUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class LoggingService implements SingletonInterface
{
  public function logFileTransferActivity(string $url, string $localFileName, string $severity = LogLevel::INFO): void
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

  public function logConnectionActivity(string $message, string $severity = LogLevel::INFO): void
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

  public function logEventActivity(Event $event, string $message, string $severity = LogLevel::INFO): void
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

  public function logObjectActivity(string $uuid, string $message, string $property, string $severity = LogLevel::INFO): void
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

  public function logSchemaActivity(string $message, string $severity = LogLevel::INFO): void
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
      [$date, $severity, $message] = explode(' ', $entry, 3) + [null, null, null];
      // Compatibility for old log levels
      if (MathUtility::canBeInterpretedAsInteger($severity)) {
        $severity = match((int)$severity) {
            4 => LogLevel::CRITICAL,
            3 => LogLevel::ERROR,
            2 => LogLevel::WARNING,
            default => LogLevel::INFO,
        };
      }

      if ($date && $severity && $message) {
        $items[] = GeneralUtility::makeInstance(LogEntry::class, $date, $severity, (string)$message);
      }
    }
    return $items;
  }

  protected function writeEntry(string $logFile, string $message, string $severity = LogLevel::INFO): void
  {
    if (empty($message)) {
      // Cowardly refusing to create an empty log message
      return;
    }
    try {
        $fp = fopen($logFile, 'a+');
    } catch (\Throwable $throwable) {
        $logger = GeneralUtility::makeInstance(LogManager::class)
            ->getLogger(self::class);
        // This error can occur in case the project has automated deployments and changes the rights of a symlink
        $logger->error('Could not open logfile "' . $logFile . '": ' . $throwable->getMessage());
        return;
    }

    // FIXME: $fp should not return boolean !!
    if ($fp !== false) {
      $validLogLevels = LogLevel::atLeast(LogLevel::WARNING);
      fwrite($fp, date('Y-m-d_H:i:s') . ' ' . $severity . ' ' . $message . PHP_EOL);
      fclose($fp);
      if (in_array($severity, $validLogLevels)) {
        $fp = fopen($this->resolveLogFilePath(ConstantsUtility::TEXT_ERRORS), 'a+');
        fwrite($fp, date('Y-m-d_H:i:s') . ' ' . LogLevel::normalizeLevel($severity) . ' ' . $message . PHP_EOL);
        fclose($fp);
      }
    }
  }

  protected function resolveLogFilePath(string $type, string $identity = null): string
  {

    $fullPath = implode(
        DIRECTORY_SEPARATOR,
        [
          Environment::getVarPath(),
          'log',
          ConstantsUtility::LOG_BASEDIR
        ]
    );

    # Create extension log file directory
    if (isset($identity)) {
      $fullPath .=  DIRECTORY_SEPARATOR . $type;
    }
    if (!is_dir($fullPath)) {
      GeneralUtility::mkdir_deep($fullPath);
    }
    if (isset($identity)) {
      $fullPath .=  DIRECTORY_SEPARATOR . $identity . '.log';
    } else {
      $fullPath .= DIRECTORY_SEPARATOR . $type . '.log';
    }

    if (!is_file($fullPath)) {
      touch($fullPath);
    }

    return GeneralUtility::getFileAbsFileName($fullPath);
  }
}
