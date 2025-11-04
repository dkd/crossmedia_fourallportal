<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Service\ModuleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fourallportal:pinschema',
    description: 'Pin PIM schema version'
)]
class PinSchemaCommand extends Command
{
    public function __construct(
        protected ModuleService $moduleService
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Pin PIM schema version')
            ->setHelp(<<< DESCRIPTION
Pins the PIM schema version, updating all local modules to use the
version of configuration that is currently live on the configured
remote server.

Used when a schema version mismatch prevents PIM sync from running.
DESCRIPTION
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->moduleService->pinSchemas();
        return Command::SUCCESS;
    }
}
