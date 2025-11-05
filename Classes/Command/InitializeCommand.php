<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Service\InitialisationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fourallportal:initialize',
    description: 'Initialize system'
)]
class InitializeCommand extends Command
{
    public function __construct(
        protected readonly InitialisationService $initialisationService,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Initialize system')
            ->setHelp(<<< DESCRIPTION
Creates Server and Module configuration if configured in
extension configuration. The array in:

\$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['fourallportal']
can contain an array of servers and modules, e.g.:
[
   'default' => [
        'domain' => '',
        'customerName' => '',
        'username' => '',
        'password' => '',
        'active' => 1,
        'modules' => [
            'module_name' => [
                'connectorName' => '',
                'mappingClass' => '',
                'shellPath' => '',
                'falStorage' => '',
                'storagePid' => '',
            ],
        ],
    ],
]

Note that the module properties may differ depending on which
mapping class the module uses, and that the server name does
not get used - it is only there to identify the entry in your
configuration file
DESCRIPTION)
            ->addOption(
                'fail',
                null,
                InputOption::VALUE_NONE,
                'Any connectivity test failure will cause the command to exit with failure'
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        $fail = (bool)($input->hasOption('fail') && $input->getOption('fail'));
        $result = Command::SUCCESS;
        try {
            $this->initialisationService->createFromVars($fail);
        } catch (\Throwable $e) {
            $result = Command::FAILURE;
        }
        $io->writeln($this->initialisationService->getLog());
        return $result;
    }
}
