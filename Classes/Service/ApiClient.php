<?php

namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Error\ApiException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ApiClient
{
  protected ?string $sessionId = null;
  protected static array $sessionPool = array();
  protected int $folderCreateMask;
  protected int $fileCreateMask;
  protected static array $lastResponse = [];
  protected mixed $portalConfig;
  protected LoggingService $loggingService;
  protected ExtensionConfiguration $extensionConfiguration;

  /**
   * @param Server $server
   * @throws ExtensionConfigurationExtensionNotConfiguredException
   * @throws ExtensionConfigurationPathDoesNotExistException
   */
  public function __construct(protected Server $server)
  {
    if (empty(self::$sessionPool)) {
      register_shutdown_function(function () {
        foreach (self::$sessionPool as $session) {
          $session->logout();
        }
      });
    }
    self::$sessionPool[] = $this;
    $this->initializeCreateMasks();
    $this->loggingService = GeneralUtility::makeInstance(LoggingService::class);
    $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
    $this->portalConfig = $this->extensionConfiguration->get('fourallportal');
  }

  /**
   * @return false|mixed|string
   * @throws ApiException
   */
  public function login(): mixed
  {
    $response = $this->doPostRequest(
      uri: $this->server->getLoginUrl(),
      data: [
        'username' => $this->server->getUsername(),
        'password' => base64_encode($this->server->getPassword()),
        'language' => 'en_US',
      ],
      persist: false
    );
    if (isset($response['session'])) {
      $this->sessionId = $response['session'];
      $this->loggingService->logConnectionActivity('login, session ID: ' . $this->sessionId);
      return $this->sessionId;
    }
    $this->loggingService->logConnectionActivity('login FAILED', 4 /*4 /*GeneralUtility::SYSLOG_SEVERITY_ERROR*/);
    return false;
  }

  /**
   * @return void
   * @throws ApiException
   */
  public function logout(): void
  {
    if ($this->sessionId === null) {
      return;
    }
    $this->doPostRequest(
      uri: $this->server->getRestUrl() . 'LoginRemoteService/logout',
      data: [
        $this->sessionId,
      ],
      persist: false
    );
    $this->loggingService->logConnectionActivity('logout, session ID: ' . $this->sessionId);
    $this->sessionId = null;
  }

  /**
   * Get configuration for a connector from MAM
   *
   * @apiparam session_id - Usersession
   * @apiparam connector_name - Name des Connectors
   *
   * @param string|null $connectorName
   * @return array $configuration
   * @throws ApiException
   */
  public function getConnectorConfig(string $connectorName = null): array
  {
    $response = $this->doPostRequest(
      uri: $this->server->getRestUrl() . 'PAPRemoteService/getConnectorConfig',
      data: [
        $this->sessionId,
        $connectorName,
      ],
      persist: false
    );
    $this->validateResponseCode($response);
    $this->loggingService->logConnectionActivity('Retrieved connector configuration for ' . $connectorName);
    return $response['result'];
  }

  /**
   * Get module configuration from MAM
   *
   * @apiparam session_id - Usersession
   * @apiparam connector_name - Name des Connectors
   *
   * @param string|null $moduleName
   * @return array $configuration
   * @throws ApiException
   */
  public function getModuleConfig(string $moduleName = null): array
  {
    $response = $this->doPostRequest(
      uri: $this->server->getRestUrl() . 'PAPRemoteService/getModuleConfig',
      data: [
        $this->sessionId,
        $moduleName,
      ],
      persist: false
    );
    $this->validateResponseCode($response);
    $this->loggingService->logConnectionActivity('Retrieved module configuration for ' . $moduleName);
    return $response['result'];
  }

  /**
   * @param string $filename
   * @param string $objectId
   * @param string|null $usage
   * @return bool|string
   * @throws ApiException
   */
  public function saveDerivate(string $filename, string $objectId, string $usage = null): bool|string
  {

    $uri = $this->server->getApiUrl() . 'modules/file/objects/' . $objectId . '/media/' . $usage;
    $sessionCookie = 'Cookie: CESESSID=' . $this->sessionId;

    $temporaryFilename = tempnam(sys_get_temp_dir(), 'fal_mam-' . $objectId);

    if (!file_exists(dirname($temporaryFilename))) {
      $oldUmask = umask(0);
      mkdir(dirname($temporaryFilename), ((int)$this - $this->folderCreateMask), true);
      umask($oldUmask);
    }

    $fp = fopen($temporaryFilename, 'w+');
    $ch = curl_init($uri);

    $temporaryHeaderbufferName = tempnam(sys_get_temp_dir(), 'header-buff' . $objectId);
    $headerBuff = fopen($temporaryHeaderbufferName, 'w+');

    curl_setopt($ch, CURLOPT_HTTPHEADER, array($sessionCookie));
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->portalConfig['clientConnectTimeout']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->portalConfig['clientTransferTimeout']);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_WRITEHEADER, $headerBuff);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->portalConfig['verifyPeer']);
    curl_setopt($ch, CURLOPT_POST, 0);

    curl_exec($ch);

    rewind($headerBuff);
    $headers = stream_get_contents($headerBuff);
    fclose($headerBuff);
    unlink($temporaryHeaderbufferName);

    $info = curl_getinfo($ch);
    if (preg_match('/filename="([^"]+)/', $headers, $matches)) {
      $filename = substr($filename, 0, strrpos($filename, '/') + 1) . $matches[1];
    }

    if (preg_match('/Content-Length:[^0-9]*([0-9]+)/', $headers, $matches)) {
      $expectedFileSize = $matches[1];
    }

    if (!empty($curlError = curl_error($ch))) {
      $message = 'CURL Failed with the Error: ' . $curlError;
      $this->loggingService->logFileTransferActivity($uri, $temporaryFilename . ': ' . $message, 4 /*GeneralUtility::SYSLOG_SEVERITY_ERROR*/);
      throw new ApiException($message, 2360915917);
    }

    if ($info['http_code'] !== 200) {
      $errorMessage = sprintf('CURL response code was %d when fetching "%s": ', $info['http_code'], $uri);
      $this->getLogger()->error(print_r($ch));
      $this->loggingService->logFileTransferActivity($uri, $temporaryFilename, 4 /*GeneralUtility::SYSLOG_SEVERITY_ERROR*/);
      throw new \TYPO3\CMS\Core\Exception($errorMessage, 3106733633);
    }

    curl_close($ch);
    fclose($fp);

    if ($expectedFileSize > 0 && $expectedFileSize != filesize($temporaryFilename)) {
      unlink($temporaryFilename);
      $message = 'The downloaded file does not match the expected filesize';
      $this->loggingService->logFileTransferActivity($uri, $temporaryFilename . ': ' . $message, 4 /*GeneralUtility::SYSLOG_SEVERITY_ERROR*/);
      throw new ApiException($message, 2780611060);
    }

    if (!file_exists(dirname($filename))) {
      $oldUmask = umask(0);
      mkdir(dirname($filename), $this->folderCreateMask, true);
      umask($oldUmask);
    }
    rename($temporaryFilename, $filename);
    chmod($filename, $this->fileCreateMask);

    $this->loggingService->logFileTransferActivity($uri, $filename);
    return $filename;
  }

  /**
   * Get events from MAM starting from a specific event id
   *
   * Dieser Service liefert nicht alle IDs aus maximal 1000.
   * Es sind alle Events ausgeliefert, sobald 0 Werte zurückgegeben werden.
   *
   * @apiparam session_id - Usersession
   * @apiparam connector_name - Name des Connectors
   * @apiparam event_id - Die Id des ersten Events
   * @apiparam config_hash - MD5. Hash der Konfiguration, um Änderungen an der Konfiguration zu erkennen.
   *
   * @param string $connectorName
   * @param integer $eventId
   * @return array $events
   *
   * id - event id
   * create_time - time of creation
   * object_id - id of the relevant object
   * object_type - type of the relevant object (0 = bean, 1 = derivate, 2 = both)
   * field_name - derivate type
   * event_type - type of event (0 = delete, 1 = update, 2 = create)
   * @throws ApiException
   */
  public function getEvents(string $connectorName, int $eventId): array
  {
    $connectorConfig = $this->getConnectorConfig($connectorName);

    $response = $this->doPostRequest(
      $uri = $this->server->getRestUrl() . 'PAPRemoteService/getEvents',
      [
        $this->sessionId,
        $connectorName,
        $eventId ? $eventId + 1 : 0,
        $connectorConfig['config_hash']
      ],
      false
    );
    switch ($response['code']) {
      case 0:
        $this->loggingService->logConnectionActivity('Events fetched for ' . $connectorName . ' since event ID ' . $eventId);
        return $response['result'];
      default:
        $message = $response['code'] . ': ' . $response['message'];
        $this->loggingService->logConnectionActivity($message, 4 /*GeneralUtility::SYSLOG_SEVERITY_ERROR*/);
        throw new ApiException($message, 4181110095);
    }
  }

  /**
   * Get events from MAM starting from a specific event id
   *
   * Dieser Service liefert nicht alle IDs aus maximal 1000.
   * Es sind alle Events ausgeliefert, sobald 0 Werte zurückgegeben werden.
   *
   * @apiparam session_id - Usersession
   * @apiparam connector_name - Name des Connectors
   * @apiparam ids - Die Ids des Beans
   *
   * @param string|array $objectIds
   * @param string $connectorName
   * @return array $beans
   * @throws ApiException
   */
  public function getBeans(string|array $objectIds, string $connectorName): iterable
  {
    if (!is_array($objectIds)) {
      $objectIds = array($objectIds);
    }

    $beans = $this->doPostRequest(
      $this->server->getRestUrl() . 'PAPRemoteService/getBeans',
      array(
        $this->sessionId,
        $connectorName,
        $objectIds,
      )
    );

    if (!isset($beans['result'][0])) {
      $this->loggingService->logConnectionActivity('Bean data request returned no results. Response: ' . json_encode($beans), 4 /*GeneralUtility::SYSLOG_SEVERITY_ERROR*/);
      throw new ApiException('Bean data request returned no results. Response: ' . json_encode($beans), 1525694885);
    }

    return $beans;
  }

  /**
   * build a remote request towards the MAM API
   *
   * @param string $method
   * @param $parameter
   * @return array
   * @throws ApiException
   * @internal param array $parameters
   */
  public function getRequest(string $method, $parameter): array
  {
    $encodedParameters = json_encode($parameter);
    $uri = $this->server->getRestUrl() . $method . '?' . http_build_query(['parameter' => $encodedParameters]);
    $response = $this->doGetRequest($uri);
    $result = json_decode($response, true);

    $this->validateResponseCode($result);

    $this->loggingService->logConnectionActivity($uri . ' ' . $encodedParameters);

    return $result['result'];
  }

  /**
   * @param string $uri
   * @param array $data
   * @param bool $persist
   * @return array
   * @throws ApiException
   */
  public function doPostRequest(string $uri, array $data, bool $persist = true): array
  {
    $ch = curl_init($uri);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->portalConfig['clientConnectTimeout']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->portalConfig['clientTransferTimeout']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->portalConfig['verifyPeer']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);

    if ($persist) {
      curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$this, 'catchResponseHeaderCallback'));
      static::$lastResponse['headers'] = [];
      static::$lastResponse['response'] = $response;
      static::$lastResponse['url'] = $uri;
      static::$lastResponse['payload'] = json_encode($data, JSON_PRETTY_PRINT);
    }
    $result = json_decode($response, true);

    $this->validateResponseCode($result);

    // Mask sensible information
    if (!empty($data['username'] ?? null)) {
        $data['username'] = '*****';
    }

    if (!empty($data['password'] ?? null)) {
        $data['password'] = '*****';
    }

    $this->loggingService->logConnectionActivity(strlen($response) . ' ApiClient.php ' . $uri . ' ' . json_encode($data));

    return $result;
  }

  /**
   * @param object $curl
   * @param string $line
   * @return integer
   */
  public function catchResponseHeaderCallback(object $curl, string $line): int
  {
    static::$lastResponse['headers'][] = $line;
    return mb_strlen($line);
  }

  /**
   * @param mixed $result
   * @throws ApiException
   */
  protected function validateResponseCode(mixed $result): void
  {
    if (!isset($result['code']) || $result['code'] != 0) {
      $message = $result['message'] ?? 'MamClient: could not communicate with mam api. please try again later';
      $this->loggingService->logConnectionActivity($message, 4 /*GeneralUtility::SYSLOG_SEVERITY_ERROR*/);
      throw new ApiException($message . ' - Response code ' . ($result['code'] ?? 'N/A') . ': ' . $this->translateResponseCode((int)($result['code'] ?? -1)), 8133411903);
    }
    if (!is_array($result)) {
      $message = 'The MAM API returned garbage data (not JSON array)';
      $this->loggingService->logConnectionActivity($message, 4 /*GeneralUtility::SYSLOG_SEVERITY_ERROR*/);
      throw new ApiException($message, 9802312867);
    }
  }

  /**
   * @param integer $code
   * @return string
   */
  protected function translateResponseCode(int $code): string
  {
    return match ($code) {
      -1 => 'UNDEFINED_ERROR',
      1 => 'PARAMETER_NOT_SET',
      2 => 'FUNCTION_NOT_IMPLEMENTED',
      default => 'Error code given but not known by the client, see REST API documentation',
    };
  }

  /**
   * execute a remote request towards the MAM API
   *
   * @param string $uri
   * @return bool|string
   */
  public function doGetRequest(string $uri): bool|string
  {
    $ch = curl_init($uri);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->portalConfig['clientConnectTimeout']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->portalConfig['clientTransferTimeout']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->portalConfig['verifyPeer']);
    static::$lastResponse['headers'] = [];
    static::$lastResponse['response'] = $result = curl_exec($ch);
    static::$lastResponse['uri'] = $uri;
    static::$lastResponse['payload'] = '';
    return $result;
  }

  protected function initializeCreateMasks(): void
  {
    if (isset($GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask'])) {
      $this->folderCreateMask = octdec($GLOBALS['TYPO3_CONF_VARS']['BE']['folderCreateMask']);
    } else {
      if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'])) {
        $this->folderCreateMask = octdec($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']);
      } else {
        $this->folderCreateMask = octdec(2775);
      }
    }

    if (isset($GLOBALS['TYPO3_CONF_VARS']['BE']['fileCreateMask'])) {
      $this->fileCreateMask = octdec($GLOBALS['TYPO3_CONF_VARS']['BE']['fileCreateMask']);
    } else {
      if (isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'])) {
        $this->fileCreateMask = octdec($GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask']);
      } else {
        $this->fileCreateMask = octdec(0664);
      }
    }
  }

  /**
   * @return array
   */
  public function getLastResponse(): array
  {
    $response = static::$lastResponse;
    $response['headers'] = trim(implode('', $response['headers'] ?? []));
    $decoded = json_decode($response['response'], true);
    if ($decoded) {
      $response['response'] = json_encode($decoded, JSON_PRETTY_PRINT);
    }
    return $response;
  }
}
