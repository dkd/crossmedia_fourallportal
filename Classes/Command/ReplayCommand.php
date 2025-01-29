<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Response\ConsoleResponse;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

#[AsCommand(
  name: 'fourallportal:replay',
  description: 'Replay events'
)]
class ReplayCommand extends Command
{

  public function __construct(
    protected ?EventExecutionService $eventExecutionService = null
  )
  {
    parent::__construct();
  }

  protected function configure()
  {
    $this
      ->setDescription('Replay events')
      ->setHelp("Replays the specified number of events, optionally only\nfor the provided module named by connector or module name.\n\nBy default, the command replays only the last event.")
      ->addArgument('module', InputArgument::REQUIRED, 'module name')
      ->addArgument('events', InputArgument::OPTIONAL, 'event id', 1)
      ->addArgument('objectId', InputArgument::OPTIONAL, 'object Id', null);
  }

  /**
   * Replay events
   *
   * Replays the specified number of events, optionally only
   * for the provided module named by connector or module name.
   *
   * By default, the command replays only the last event.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int
   * @throws Exception
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());

    $module = (string)$input->getArgument('module');
    $events = (int)$input->getArgument('events');
    $objectId = $input->getArgument('objectId');

    $consoleResponse = new ConsoleResponse($io);
    $this->eventExecutionService->setResponse($consoleResponse);
    $this->eventExecutionService->replay($events, $module, $objectId);

    return Command::SUCCESS;
  }

}
