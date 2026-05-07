<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Crossmedia\Fourallportal\Command\Event;

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Response\ConsoleResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fourallportal:event:execute',
    description: 'Execute pending events or a given single event'
)]
class ExecuteCommand extends AbstractEventCommand
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setDescription('Execute events')
            ->setHelp("Execute pending events or a given single event or object")
            ->addOption(
                'object',
                'o',
                InputOption::VALUE_REQUIRED,
                'The object id to process. Executes the last event.',
            )
            ->addOption(
                'event',
                'e',
                InputOption::VALUE_REQUIRED,
                'ID of the event to be executed. In case an object is given, the event will be ignored',
            )
            ->addOption(
                'no-deferred-events',
                'nd',
                InputOption::VALUE_NONE,
                'Ignores deferred events (if option event is omitted).' . PHP_EOL .
                '[Warning] Use this option only if the deferred events are not related to the event that should be processed.',
            )
            ->addOption(
                'rebuild-relations',
                'b',
                InputOption::VALUE_NONE,
                'Drops all PIM related relations and rebuild them (if option event is set).',
            )
        ;
    }

    /**
     * Configure the sync and execute parameters
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        $noDeferredEvents = $input->hasOption('no-deferred-events') && $input->getOption('no-deferred-events');
        $rebuildRelations = $input->hasOption('rebuild-relations') && $input->getOption('rebuild-relations');
        $eventId = $input->getOption('event');
        $objectId = $input->getOption('object');

        $single = !empty($eventId) || !empty($objectId);

        if ($noDeferredEvents && !$single) {
            $this->parameters->withoutDeferredEvents();
            $io = new SymfonyStyle($input, $output);
            $io->warning('Deferred events are ignored, this may have side effects!');
        }
        if ($rebuildRelations && $single) {
            $this->parameters->setDropAllRelations(true);
        }
        $this->parameters
            ->setSync(false)
            ->setFullSync(false)
            ->setExecute(true)
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());

        $eventId = $input->getOption('event');
        $objectId = $input->getOption('object');

        /** @var Event|null $event */
        $event = null;
        if (!empty($objectId)) {
            $event = $this->eventRepository->findLastEventByObjectId((string)$objectId);
            if ($event->getEventType() === 'delete') {
                $event = null;
            }
            if (empty($event)) {
                if ($output->isVerbose()) {
                    $io->writeln('Event for object ' . $objectId . ' not found.');
                }
                return Command::INVALID;
            }
        } elseif (!empty($eventId)) {
            $event = $this->eventRepository->findByEventId((int)$eventId);
            if (empty($event)) {
                if ($output->isVerbose()) {
                    $io->writeln('Event ' . $eventId . ' not found.');
                }
                return Command::INVALID;
            }
        }

        if ($this->maxThreads > 0 && empty($event)) {
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

        if (!$this->parameters->getForce()) {
            try {
                $this->eventExecutionService->lock();
            } catch (\Exception $error) {
                $io->writeln('Cannot acquire lock - exiting error' . PHP_EOL);
                return Command::FAILURE;
            }
        }

        $consoleResponse = new ConsoleResponse($io);
        $this->eventExecutionService->setResponse($consoleResponse);

        $result = Command::SUCCESS;

        if (!empty($event)) {
            $event->setProcessing(false);
            try {
                $this->eventExecutionService->processEvent($event, false, $this->parameters);
            } catch (\Throwable $throwable) {
                $this->eventExecutionService->handleEventException($event, $throwable);
                $result = Command::FAILURE;
                if (!$output->isQuiet()) {
                    $this->logger->critical($throwable->getMessage());
                }
            }
        } else {
            try {
                $this->eventExecutionService->sync($this->parameters);
            } catch (\Throwable $throwable) {
                $result = Command::FAILURE;
                if (!$output->isQuiet()) {
                    $this->logger->critical($throwable->getMessage());
                }
            }
        }

        if (!$this->parameters->getForce()) {
            $this->eventExecutionService->unlock();
        }
        return $result;
    }
}
