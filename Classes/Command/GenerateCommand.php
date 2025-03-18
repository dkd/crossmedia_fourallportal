<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ConfigGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;

#[AsCommand(
    name: 'fourallportal:generate',
    description: 'Generates all configuration'
)]
class GenerateCommand extends Command
{
    public function __construct(
        protected ?ConfigGeneratorService $configGeneratorService = null
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Generates all configuration')
            ->setHelp(<<< DESCRIPTION
Generates all configuration

Shortcut method for calling all of the three specific
generate commands to generate static configuration files for
all dynamic-model-enabled modules' entities.

Important:
Clear the TYPO3 cache before running an import
DESCRIPTION)
            ->addArgument('entityClassName', InputArgument::REQUIRED, 'Name of the entity class. Use two back slashes on the command line')
            ->addOption(
                'strict',
                null,
                InputOption::VALUE_NONE,
                'Generates strict PHP code'
            )
            ->addOption(
                'read-only',
                null,
                InputOption::VALUE_NONE,
                'Generates TCA fields as read-only'
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $entityClassName = (string)$input->getArgument('entityClassName');
        $strict = (bool)($input->hasOption('strict') && $input->getOption('strict'));
        $readOnly = (bool)($input->hasOption('read-only') && $input->getOption('read-only'));

        $this->configGeneratorService->setOutputInterface($output);
        try {
            $this->configGeneratorService->generateSqlSchemaCommand();
            $this->configGeneratorService->generateTableConfiguration($entityClassName, $readOnly);
            $this->configGeneratorService->generateAbstractModelClassCommand($io, $entityClassName, $strict);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
