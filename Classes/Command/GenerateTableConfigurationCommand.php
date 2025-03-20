<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Service\ConfigGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;

#[AsCommand(
    name: 'fourallportal:generateTableConfiguration',
    description: 'Generate TCA for model'
)]
class GenerateTableConfigurationCommand extends Command
{
    public function __construct(
        protected ?ConfigGeneratorService $configGeneratorService = null
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Generate TCA for model')
            ->setHelp(<<< DESCRIPTION
This command can be used instead or together with the
dynamic model feature to generate a TCA file for a particular
entity, by its class name.

Internally the class name is analysed to determine the
extension it belongs to, and makes an assumption about the
table name. The command then writes the generated TCA to the
exact TCA configuration file (by filename convention) and
will overwrite any existing TCA in that file.

Should you need to adapt individual properties such as the
field used for label, the icon path etc. please use the
Configuration/TCA/Overrides/\$tableName.php file instead.
DESCRIPTION)
            ->addArgument('entityClassName', InputArgument::REQUIRED, 'Name of the entity class. Use two back slashes on the command line')
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
        if ((bool)($input->hasOption('read-only') && $input->getOption('read-only'))) {
            $this->configGeneratorService->enableReadOnly();
        }
        $this->configGeneratorService->generateTableConfiguration($entityClassName);
        return Command::SUCCESS;
    }
}
