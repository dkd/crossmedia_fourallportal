<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Command\Event\AbstractEventCommand;
use Crossmedia\Fourallportal\Domain\Dto\SyncParameters;
use Crossmedia\Fourallportal\Response\ConsoleResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fourallportal:sync',
    description: 'Sync data',
    aliases: ['fourallportal:event:sync-execute']
)]
class SyncCommand extends AbstractEventCommand
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this
            ->setDescription('Sync data')
            ->setHelp("Execute this to synchronise events from the PIM API")
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
                'execute',
                null,
                InputOption::VALUE_NONE,
                'Executes events after receiving (syncing) events'
            )
        ;
        parent::configure();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $sync = $input->hasOption('sync') && $input->getOption('sync');
        $fullSync = $input->hasOption('full-sync') && $input->getOption('full-sync');
        $execute = $input->hasOption('execute') && $input->getOption('execute');

        $this->parameters
            ->setSync($sync)
            ->setFullSync($fullSync)
            ->setExecute($execute)
        ;

        // If option sync is enabled
        if ($this->parameters->getFullSync() && !$this->parameters->getSync()) {
            $this->parameters->setSync(true);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        if (!$this->parameters->getSync() && !$this->parameters->getExecute()) {
            $io->writeln('Either option --sync, --full-sync or --execute has to be used' . PHP_EOL);
            return Command::INVALID;
        }

        if (!$this->parameters->getSync()) {
            // We are executing only, not syncing. Check number of currently running threads - if no more threads are
            // allowed, exit early.
            $currentThreadCount = $this->eventRepository->count(
                [
                    'processing' => true
                ]
            );
            if ($currentThreadCount >= $this->maxThreads) {
                if ($output->isVerbose()) {
                    $io->writeln('Maximum allowed threads of ' . $this->maxThreads . ' reached.');
                }
                return Command::SUCCESS;
            }
        }

        if (!$this->parameters->getForce() && $this->parameters->getSync()) {
            try {
                $this->eventExecutionService->lock();
            } catch (\Exception $error) {
                $io->writeln('Cannot acquire lock - exiting error' . PHP_EOL);
                return Command::FAILURE;
            }
        }

        $consoleResponse = new ConsoleResponse($io);
        $this->eventExecutionService->setResponse($consoleResponse);
        $this->eventExecutionService->sync($this->parameters);

        if (!$this->parameters->getForce() && $this->parameters->getSync()) {
            $this->eventExecutionService->unlock();
        }
        return Command::SUCCESS;
    }
}
