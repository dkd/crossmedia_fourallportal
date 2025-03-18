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

#[AsCommand(
    name: 'fourallportal:generateAbstractModelClass',
    description: 'Generate abstract entity class'
)]
class GenerateAbstractModelClassCommand extends Command
{
    public function __construct(
        protected ?ConfigGeneratorService $configGeneratorService = null
    ) {
        parent::__construct();
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setDescription('Generate abstract entity class')
            ->setHelp(<<< DESCRIPTION
Generate abstract entity class

This command can be used as substitute for the automatic
model class generation feature. Each entity class generated
with this command prevents usage of the dynamically created
class (which still gets created!). To re-enable dynamic
operation simply remove the generated abstract class again.

Generates an abstract PHP class in the same namespace as
the input entity class name. The abstract class contains
all the dynamically generated properties associated with
the Module.

Important:
Clear the TYPO3 cache before running an import
DESCRIPTION)
            ->addArgument('entityClassName', InputArgument::REQUIRED, 'Name of the entity class. Use two back slashes on the command line')
            ->addOption(
                'strict',
                null,
                InputOption::VALUE_NONE,
                'Generates strict PHP code'
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

        $this->configGeneratorService->generateAbstractModelClassCommand($io, $entityClassName, $strict);
        return Command::SUCCESS;
    }
}
