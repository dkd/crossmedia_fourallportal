<?php

namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\Exception;
use Crossmedia\Fourallportal\Exceptions\ApiLoginException;
use Crossmedia\Fourallportal\Exceptions\InvalidModuleConfigurationException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class InitialisationService
{
    protected array $log = [];

    public function __construct(
        protected ModuleRepository $moduleRepository,
        protected PersistenceManager $persistenceManager,
        protected ServerRepository $serverRepository
    ) {
    }

    /**
     * Create the server configuration baed on the TYPO3_CONF_VARS data
     *
     * @return void
     */
    public function createFromVars(bool $fail = false): void
    {
        $this->log = [];
        // Retrieve whole configuration
        $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('fourallportal');

        if (isset($settings['servers'])) {
            foreach ($settings['servers'] as $connectionName => $server) {
                $currentServer = $this->serverRepository->findOneBy(['domain' => $server['domain']]);
                if (!$currentServer) {
                    $this->log[] = 'Creating new server for ' . $server['domain'];
                    $currentServer = new Server();
                    $this->serverRepository->add($currentServer);
                } else {
                    $this->log[] = 'Updating configuration for ' . $server['domain'];
                    $this->serverRepository->update($currentServer);
                }

                if ($server['username']) {
                    $currentServer->setUsername($server['username']);
                    $this->log[] = '* Username: ' . $server['username'];
                }

                if ($server['password']) {
                    $currentServer->setPassword($server['password']);
                    $this->log[] = '* Password: ****';
                }

                $currentServer->setActive((bool)$server['active']);
                $this->log[] = '* Active: ' . $server['active'];

                $currentServer->setCustomerName($server['customerName'] ?? $connectionName);
                $this->log[] = '* Customer name: ' . $server['customerName'];

                $currentServer->setDomain($server['domain']);

                try {
                    $currentServer->getClient()->login();
                    $this->log[] = '* Testing connectivity...OKAY!';
                } catch (Exception $error) {
                    $this->log[] = '* Testing connectivity...ERROR! ' . $error->getMessage();
                    if ($fail) {
                        throw new ApiLoginException('Login for connection "' . $currentServer->getCustomerName() . '" failed');
                    }
                }
                $this->log[] = '';

                foreach ($server['modules'] as $moduleName => $moduleProperties) {
                    $module = $this->ensureServerHasModule($currentServer, $moduleName, $moduleProperties);
                    $module->setServer($currentServer);

                    try {
                        $module->getModuleConfiguration();
                        $this->log[] = '* Testing connectivity...OKAY!';
                    } catch (Exception $error) {
                        $this->log[] = '* Testing connectivity...ERROR! ' . $error->getMessage();
                        if ($fail) {
                            throw new InvalidModuleConfigurationException('[Module ' . $moduleName . '] ' . $error->getMessage());
                        }
                    }
                    $this->log[] = '';
                }
            }
        }
        $this->persistenceManager->persistAll();
    }

    /**
     * Reconnects the server with already existing modules
     *
     * @param int $serverUid
     * @return void
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function reconnectModulesToServerByUid(int $serverUid): void
    {
        $server = $this->serverRepository->findByUid($serverUid);
        if (!$server instanceof Server) {
            return;
        }

        $modules = $this->moduleRepository->findBy(['server' => $server->getUid()]);
        foreach ($modules as $module) {
            $module->setServer($server);
            $this->moduleRepository->update($module);
            $server->addModule($module);
        }
        $this->serverRepository->update($server);
        $this->persistenceManager->persistAll();
    }

    /**
     * @param Server $server
     * @param string $moduleName
     * @param array $moduleProperties
     * @return Module
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    protected function ensureServerHasModule(
        Server $server,
        string $moduleName,
        array $moduleProperties
    ): Module {
        $currentModule = new Module();
        $foundModule = false;
        foreach ($server->getModules() as $module) {
            if ($module->getModuleName() === $moduleName) {
                $foundModule = true;
                $currentModule = $module;
                break;
            }
        }
        if (!$foundModule) {
            $this->log[] = 'Adding new module for ' . $moduleName;
        } else {
            $this->log[] = 'Updating existing module configuration for ' . $moduleName;
        }

        $currentModule->setModuleName($moduleName);

        $currentModule->setConnectorName($moduleProperties['connectorName']);
        $this->log[] = '* Connector name: ' . $moduleProperties['connectorName'];

        $currentModule->setMappingClass($moduleProperties['mappingClass']);
        $this->log[] = '* Mapping class: ' . $moduleProperties['mappingClass'];

        $currentModule->setEnableDynamicModel($moduleProperties['enableDynamicModel'] ?? false);
        $this->log[] = '* Dynamic: ' . $moduleProperties['enableDynamicModel'];

        if ($moduleProperties['shellPath'] ?? false) {
            $currentModule->setShellPath($moduleProperties['shellPath']);
            $this->log[] = '* Shell path: ' . $moduleProperties['shellPath'];
        }

        if ($moduleProperties['falStorage'] ?? false) {
            $currentModule->setFalStorage((int)$moduleProperties['falStorage']);
            $this->log[] = '* FAL storage: ' . $moduleProperties['falStorage'];
        }

        if ($moduleProperties['storagePid'] ?? false) {
            $currentModule->setStoragePid((int)$moduleProperties['storagePid']);
            $this->log[] = '* Storage PID: ' . $moduleProperties['storagePid'];
        }

        if ($currentModule->getUid()) {
            $this->moduleRepository->update($currentModule);
        } else {
            $this->moduleRepository->add($currentModule);
            $server->getModules()->attach($currentModule);
        }
        return $currentModule;
    }

    /**
     * Returns the log data
     *
     * @return array
     */
    public function getLog(): array
    {
        return $this->log;
    }
}
