<?php

namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class ModuleService
{
    public function __construct(
        protected ServerRepository $serverRepository,
        protected ModuleRepository $moduleRepository,
        protected PersistenceManager $persistenceManager,
    ) {
    }

    /**
     * Validate all module hashes
     *
     * @return bool
     */
    public function validateAllSchemas(): bool
    {
        $result = true;

        try {
            foreach ($this->getServers() as $server) {
                /** @var Server $server */
                if (!($server instanceof Server)) {
                    continue;
                }
                foreach ($server->getModules() as $module) {
                    print $module->getModuleName() . PHP_EOL;
                    /** @var Module $module*/
                    if (!$module->verifySchemaVersion()) {
                        $result = false;
                    }
                }
            }
        } catch (\Throwable $e) {
            print $e->getMessage() . PHP_EOL;
            return false;
        }

        return $result;
    }

    /**
     * Generate a list of modules, the local and foreign hash.
     * Puts the test result in the end.
     *
     * @return array
     */
    public function getModuleHashes(): array
    {
        $tableRows = [];

        try {
            foreach ($this->moduleRepository->findAll() as $module) {
                /** @var Module $module*/
                $tableRows[] = [
                    $module->getModuleName(),
                    $module->getConfigHash(),
                    $module->getConnectorConfiguration()['config_hash'] ?? '',
                    $module->verifySchemaVersion() ? 'yes' : 'no',
                ];
            }
        } catch (\Throwable $e) {
        }
        return $tableRows;
    }

    /**
     * Pin all schemas
     */
    public function pinSchemas(): void
    {
        foreach ($this->getServers() as $server) {
            /** @var Server $server */
            if (!($server instanceof Server)) {
                continue;
            }
            foreach ($server->getModules() as $module) {
                $module->pinSchemaVersion();
                $this->persistenceManager->update($module);
            }
        }
        $this->persistenceManager->persistAll();
    }

    protected function getServers(int|null $uid = null): \Traversable
    {
        if (empty($id)) {
            $servers = $this->serverRepository->findBy(['active' => true]);
            foreach ($servers as $server) {
                yield $server;
            }
        } else {
            $server = $this->serverRepository->findByUid($uid);
            yield $server;
        }
    }
}
