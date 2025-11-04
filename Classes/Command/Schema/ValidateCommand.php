<?php

namespace Crossmedia\Fourallportal\Command\Schema;

use Crossmedia\Fourallportal\Service\ModuleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fourallportal:schema:validate',
    description: 'Validate the schema versions for each module'
)]
class ValidateCommand extends Command
{
    public function __construct(
        protected ModuleService $moduleService
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Validate the lokal module schema with the remote')
            ->setHelp(<<< DESCRIPTION
Use this command to verify if youre modules are up to date.
It can be used within an CI pipeline.

In case everything is up to date, this command will return success.
In case the schema is outdated, the command will return an invalid result.
In other cases the command will fail with an error.
DESCRIPTION
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);


        $result = $this->moduleService->validateAllSchemas() ?
            Command::SUCCESS :
            Command::INVALID;

        if ($output->isVerbose() && !$output->isQuiet()) {
            $tableHeader = [
                'Module',
                'Local hash',
                'Remote hash',
                'Valid',
            ];
            $tableRows = $this->moduleService->getModuleHashes();
            $io->table($tableHeader, $tableRows);
        }

        return $result;
    }
}
