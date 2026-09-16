<?php

namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Error\ApiTimeOutException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ApiClient
{
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
        $this->initializeCreateMasks();
        $this->loggingService = GeneralUtility::makeInstance(LoggingService::class);
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->portalConfig = $this->extensionConfiguration->get('fourallportal');
    }

    /**
     * @return string|false
     * @throws ApiException
     */
    public function login(): string|false
    {
        $response = $this->doGetRequest($this->server->getApiUrl() . 'connectors');
        $result = json_decode($response, true);
        if (is_array($result)) {
            $this->loggingService->logConnectionActivity('Bearer Token valid - connectors reachable');
            return true;
        }
        $this->loggingService->logConnectionActivity('Bearer Token invalid - connectors not reachable', LogLevel::CRITICAL);
        return false;
    }

    /**
     * Get configuration for a connector from MAM
     *
     * @param string|null $connectorName
     * @param bool $withFieldsToLoad - Whether to fetch and build fieldsToLoad from module configuration
     * @return array $configuration
     * @throws ApiException
     */
    public function getConnectorConfig(string $connectorName = null, bool $withFieldsToLoad = false): array
    {
        $response = $this->doGetRequest(
            $this->server->getApiUrl() . 'connectors/' . $connectorName
        );

        $result = json_decode($response, true);
        $this->validateResponseCode($result);

        if ($withFieldsToLoad && !empty($result['fields']) && !empty($result['module_name'])) {
            $moduleConfig = $this->getModuleConfig($result['module_name']);
            $moduleFieldsByName = [];
            foreach ($moduleConfig['fields'] ?? [] as $field) {
                $moduleFieldsByName[$field['name']] = $field;
            }

            $fieldsToLoad = [];
            foreach ($result['fields'] as $fieldName) {
                if (isset($moduleFieldsByName[$fieldName])) {
                    $fieldsToLoad[$fieldName] = $moduleFieldsByName[$fieldName];
                }
            }
            $result['fieldsToLoad'] = $fieldsToLoad;
        }

        $this->loggingService->logConnectionActivity('Retrieved connector configuration for ' . $connectorName);
        return $result;
    }

    /**
     * Get module configuration from MAM
     *
     * @param string|null $moduleName
     * @param bool $withRelationConf - Whether to fetch and build relation_conf from value-options endpoint
     * @return array $configuration
     * @throws ApiException
     */
    public function getModuleConfig(string $moduleName = null, bool $withRelationConf = false): array
    {
        $response = $this->doGetRequest(
            $this->server->getApiUrl() . 'modules/' . $moduleName . '?groups=fields'
        );
        $result = json_decode($response, true);
        $this->validateResponseCode($result);

        $result['fields'] = array_column($result['fields'] ?? [], null, 'name');

        if ($withRelationConf) {
            $response = $this->doGetRequest(
                $this->server->getApiUrl() . 'system/value-options?ids=' . $moduleName . '.referenced_by'
            );
            $valueOptions = json_decode($response, true);
            $result['relation_conf'] = $this->buildRelationConfFromValueOptions(
                $valueOptions[$moduleName . '.referenced_by'] ?? [],
                $moduleName
            );
        }

        $this->loggingService->logConnectionActivity('Retrieved module configuration for ' . $moduleName);
        return $result;
    }

    protected function buildRelationConfFromValueOptions(array $valueOptions, string $currentModuleName): array
    {
        $relationConf = [];

        foreach ($valueOptions['groups'] ?? [] as $group) {
            $parentModule = $group['key'];
            foreach ($group['items'] ?? [] as $item) {
                $itemKey = $item['key'];
                $icon = $item['icon'] ?? '';

                // Derive type from icon
                $type = match($icon) {
                    'V-FIELD_LINK'  => 'FIELD_LINK',
                    'V-OBJECT_LINK' => 'OBJECT_LINK',
                    default         => 'FIELD_LINK',
                };

                // field name: strip parentModule prefix from itemKey
                // e.g. "hedgehog_product" with parent "hedgehog" -> field = "product"
                $field = $itemKey;
                if (str_starts_with($itemKey, $parentModule . '_')) {
                    $field = substr($itemKey, strlen($parentModule) + 1);
                }

                $relationConf[$itemKey] = [
                    'type'           => $type,
                    'name'           => $itemKey,
                    'field'          => $field,
                    'parent'         => $parentModule,
                    'child'          => $currentModuleName,
                    'relatedModule'  => $parentModule,
                ];
            }
        }

        return $relationConf;
    }

    /**
     * @param string $filename
     * @param string $objectId
     * @param string|null $usage
     * @return bool|string
     * @throws ApiException
     * @throws ApiTimeOutException
     */
    public function saveDerivate(string $filename, string $objectId, string $usage = null): bool|string
    {

        $uri = $this->server->getApiUrl() . 'modules/file/objects/' . $objectId . '/media/' . $usage;

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

        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->portalConfig['clientConnectTimeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->portalConfig['clientTransferTimeout']);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_WRITEHEADER, $headerBuff);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->portalConfig['verifyPeer']);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->server->getPassword(),
        ]);

        curl_exec($ch);

        rewind($headerBuff);
        $headers = stream_get_contents($headerBuff);
        fclose($headerBuff);
        unlink($temporaryHeaderbufferName);

        $info = curl_getinfo($ch);
        if (preg_match('/filename="([^"]+)/', $headers, $matches)) {
            $filename = substr($filename, 0, strrpos($filename, '/') + 1) . $matches[1];
        }
        $expectedFileSize = 0;
        if (preg_match('/Content-Length:[^0-9]*([0-9]+)/', $headers, $matches)) {
            $expectedFileSize = $matches[1];
        }

        if (!empty($curlError = curl_error($ch))) {
            $message = 'CURL Failed with the Error: ' . $curlError;
            $this->loggingService->logFileTransferActivity($uri, $temporaryFilename . ': ' . $message, LogLevel::CRITICAL);
            if (str_contains($curlError, 'Operation timed out')) {
                throw new ApiTimeOutException(
                    $curlError,
                    1742823778
                );
            }
            throw new ApiException($message, 2360915917);
        }

        if ($info['http_code'] !== 200) {
            $errorMessage = sprintf('CURL response code was %d when fetching "%s": ', $info['http_code'], $uri);
            $this->loggingService->logFileTransferActivity($uri, $temporaryFilename, LogLevel::CRITICAL);
            throw new \TYPO3\CMS\Core\Exception($errorMessage, 3106733633);
        }

        curl_close($ch);
        fclose($fp);

        if ($expectedFileSize > 0 && $expectedFileSize != filesize($temporaryFilename)) {
            unlink($temporaryFilename);
            $message = 'The downloaded file does not match the expected filesize';
            $this->loggingService->logFileTransferActivity($uri, $temporaryFilename . ': ' . $message, LogLevel::CRITICAL);
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
     * @apiparam connector_name - Name of the connector
     * @apiparam id - The last known event id
     * @apiparam hash - Hash of the configuration to determine possible changes of the configuration.
     *
     * @param string $connectorName
     * @param integer $eventId
     * @return array $events
     *
     * id - event id
     * mod_time - time of modification
     * object_id - id of the relevant object
     * object_type - type of the relevant object
     * event_type - type of event (0 = delete, 1 = update, 2 = create)
     * @throws ApiException
     */
    public function getEvents(string $connectorName, int $eventId): array
    {
        $connectorConfig = $this->getConnectorConfig($connectorName);

        $response = $this->doGetRequest(
            $this->server->getApiUrl() . 'connectors/' . $connectorName . '/events/' . ($eventId ? $eventId + 1 : 0) . '?hash=' . $connectorConfig['hash']
        );

        $result = json_decode($response, true);
        $this->validateResponseCode($result);

        $moduleName = $connectorConfig['module_name'] ?? '';
        foreach ($result as &$event) {
            $event['module_name'] = $moduleName;
        }
        unset($event);

        $this->loggingService->logConnectionActivity('Events fetched for ' . $connectorName . ' since event ID ' . $eventId);
        return $result ?? [];
    }

    /**
     * Get events from MAM starting from a specific event id
     *
     * This service did not deliver all IDs. The maximum amount is 1000.
     * All event have been returned, in case 0 is returned.
     *
     * @apiparam connector_name - Name of the connector
     * @apiparam ids - The IDs of the objects
     *
     * @param string|array $objectIds
     * @param string $connectorName
     * @return array $beans
     * @throws ApiException
     */
    public function getBeans(string|array $objectIds, string $connectorName): iterable
    {
        if (!is_array($objectIds)) {
            $objectIds = [$objectIds];
        }

        $idsParam = implode('&ids=', array_map('urlencode', $objectIds));
        $response = $this->doGetRequest(
            $this->server->getApiUrl() . 'connectors/' . $connectorName . '/objects?ids=' . $idsParam
        );

        $result = json_decode($response, true);
        $this->validateResponseCode($result);

        $reserved = array_flip(['id', 'module', 'type', 'mod_time']);
        $transformed = [];
        foreach ($result as $object) {
            $transformed[] = [
                'id'          => $object['id'],
                'module_name' => $object['module'],
                'mod_time'    => $object['mod_time'],
                'type'        => $object['type'],
                'properties'  => array_diff_key($object, $reserved),
            ];
        }

        $beans = ['result' => $transformed];

        if (!isset($beans['result'][0])) {
            $this->loggingService->logConnectionActivity('Bean data request returned no results. Response: ' . json_encode($beans), LogLevel::CRITICAL);
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
            'Authorization: Bearer ' . $this->server->getPassword(),
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
        if (!is_array($result)) {
            $message = 'The MAM API returned garbage data. Expected JSON array, got "' . gettype($result) . '"';
            $this->loggingService->logConnectionActivity($message, LogLevel::CRITICAL);
            throw new ApiException($message, 9802312867);
        }
        if (isset($result['status']) && (int)$result['status'] >= 400) {
            $message = $result['message'] ?? 'MamClient: could not communicate with mam api. please try again later';
            $this->loggingService->logConnectionActivity($message, LogLevel::CRITICAL);
            throw new ApiException($message . ' - Response code ' . ($result['code'] ?? 'N/A') . ': ' . $this->translateResponseCode((int)($result['code'] ?? -1)), 8133411903);
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
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$this->portalConfig['clientConnectTimeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int)$this->portalConfig['clientTransferTimeout']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool)$this->portalConfig['verifyPeer']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->server->getPassword(),
        ]);
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
        $decoded = json_decode($response['response'] ?? '', true);
        if ($decoded) {
            $response['response'] = json_encode($decoded, JSON_PRETTY_PRINT);
        }
        return $response;
    }
}
