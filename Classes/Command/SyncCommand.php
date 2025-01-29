<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Dto\SyncParameters;
use Crossmedia\Fourallportal\Response\ConsoleResponse;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

#[AsCommand(
    name: 'fourallportal:sync',
    description: 'Sync data'
)]
class SyncCommand extends Command
{

    public function __construct(
        protected ?EventExecutionService $eventExecutionService = null,
        protected ?ConnectionPool $connectionPool = null,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setDescription('Sync data')
            ->setHelp("Execute this to synchronise events from the PIM API")
            ->addArgument('module', InputArgument::OPTIONAL, 'If passed can be used to only sync one module, using the module or connector name it has in 4AP', null)
            ->addArgument('exclude', InputArgument::OPTIONAL, 'Exclude a list of modules from processing (CSV string module names)', null)
            ->addArgument('maxEvents', InputArgument::OPTIONAL, 'Maximum number of events to process. Default is unlimited. Affects only the number of events being executed, if sync is enabled will still sync all', 0)
            ->addArgument('maxTime', InputArgument::OPTIONAL, 'Maximum number of seconds that the sync is allowed to run, once expired, will require a new execution to continue', 0)
            ->addArgument('maxThreads', InputArgument::OPTIONAL, 'Maximum number of concurrent threads which are allowed to execute events. Ignored if sync=true', 4)
            ->addOption(
                'sync',
                null,
                InputOption::VALUE_NONE,
                'Sync events (starting from last received event). If execute=true will happen before executing'
            )
            ->addOption(
                'full-sync',
                null,
                InputOption::VALUE_NONE,
                'Trigger a full sync'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Forces the sync to run regardless of lock and will neither lock nor unlock the task'
            )
            ->addOption(
                'execute',
                null,
                InputOption::VALUE_NONE,
                'Executes events after receiving (syncing) events'
            );
    }

    /**
     * Sync data
     *
     * Execute this to synchronise events from the PIM API.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $sync = $input->hasOption('sync') && $input->getOption('sync');
        $fullSync = $input->hasOption('full-sync') && $input->getOption('full-sync');
        $module = $input->getArgument('module');
        $exclude = (string)$input->getArgument('exclude');
        $force = $input->hasOption('force') && $input->getOption('force');
        $execute = $input->hasOption('execute') && $input->getOption('execute');
        $maxEvents = (int)$input->getArgument('maxEvents');
        $maxTime = (int)$input->getArgument('maxTime');
        $maxThreads = (int)$input->getArgument('maxThreads');

        // If option sync is enabled
        if ($fullSync && !$sync) {
            $sync = true;
        }

        if (!$sync && !$execute) {
            $io->writeln('Either option --sync, --full-sync or --execute has to used' . PHP_EOL);
            return Command::INVALID;
        }

        if (!$sync) {
            // We are executing only, not syncing. Check number of currently running threads - if no more threads are
            // allowed, exit early.
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_fourallportal_domain_model_event');
            $query = $queryBuilder->select('uid')
                ->from('tx_fourallportal_domain_model_event')
                ->where($queryBuilder->expr()->eq('processing', 1));
            $currentThreadCount = $query->executeQuery()->rowCount();
            if ($currentThreadCount >= $maxThreads) {
                return Command::SUCCESS;
            }
        }
        if (!$force && $sync) {
            try {
                $this->eventExecutionService->lock();
            } catch (\Exception $error) {
                $io->writeln('Cannot acquire lock - exiting without error' . PHP_EOL);
                return Command::FAILURE;
            }
        }

        $syncParameters = GeneralUtility::makeInstance(SyncParameters::class)
            ->setSync($sync)
            ->setFullSync($fullSync)
            ->setModule($module)
            ->setExclude($exclude)
            ->setForce($force)
            ->setExecute($execute)
            ->setEventLimit($maxEvents)
            ->setTimeLimit($maxTime);

        $consoleResponse = new ConsoleResponse($io);
        $this->eventExecutionService->setResponse($consoleResponse);
        $this->eventExecutionService->sync($syncParameters);

        if (!$force && $sync) {
            $this->eventExecutionService->unlock();
        }

        return Command::SUCCESS;
    }
}
