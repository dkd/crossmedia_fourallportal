<?php

namespace Crossmedia\Fourallportal\Service;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;

class ModuleService
{
    public function __construct(
        protected ModuleRepository $moduleRepository
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
            foreach ($this->moduleRepository->findAll() as $module) {
                /** @var Module $module*/
                if (!$module->verifySchemaVersion()) {
                    $result = false;
                }
            }
        } catch (\Throwable $e) {
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
}