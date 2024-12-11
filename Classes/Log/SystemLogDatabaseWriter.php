<?php
declare(strict_types=1);

namespace Crossmedia\Fourallportal\Log;

use Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Writer\DatabaseWriter;
use TYPO3\CMS\Core\Log\Writer\WriterInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Replacement for DatabaseWriter which writes entries
 * that show up correctly in the log module in BE.
 * In order to make them sort and use proper labels,
 * this override has to assign additional columns
 * compared to the original class' method.
 *
 * Registered in ext_localconf.php
 */
class SystemLogDatabaseWriter extends DatabaseWriter
{
  public function __construct(private readonly ConnectionPool $connectionPool, array $options = [])
  {
    parent::__construct($options);
  }

  public function writeLog(LogRecord $record): WriterInterface|static
  {
    $data = '';
    $recordData = $record->getData();
    if (!empty($recordData)) {
      // According to PSR3 the exception-key may hold an \Exception
      // Since json_encode() does not encode an exception, we run the _toString() here
      if (isset($recordData['exception']) && $recordData['exception'] instanceof Exception) {
        $recordData['exception'] = (string)$recordData['exception'];
      }
      $data = '- ' . json_encode($recordData);
    }

    $fieldValues = [
      'tstamp' => time(),
      'type' => 5,
      'ip' => GeneralUtility::getIndpEnv('REMOTE_ADDR') ?? GeneralUtility::getIndpEnv('HTTP_CLIENT_IP') ?? '',
      'details' => $record->getMessage(),
      'request_id' => $record->getRequestId(),
      'time_micro' => $record->getCreated(),
      'component' => $record->getComponent(),
      'error' => $record->getLevel(),
      'event_pid' => -1,
      'level' => $record->getLevel(),
      'message' => $record->getMessage(),
      'data' => $data,
      'log_data' => serialize($recordData),
      'userid' => $GLOBALS['BE_USER']->id,
    ];

    $this->connectionPool->getConnectionForTable($this->logTable)
      ->insert($this->logTable, $fieldValues);

    return $this;
  }
}
