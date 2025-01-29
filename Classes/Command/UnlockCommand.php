<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Response\ConsoleResponse;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'fourallportal:unlock',
  description: 'Unlock sync'
)]
class UnlockCommand extends Command
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
      ->setDescription("Unlock sync")
      ->setHelp("Removes a (stale) lock.")
      ->addArgument('requiredAge', InputArgument::OPTIONAL, 'Number of seconds, required minimum age of the lock file before removal will be allowed', 0);
  }

  /**
   * Unlock sync
   *
   * Removes a (stale) lock.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int
   * @throws \Doctrine\DBAL\Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());

    $requiredAge = (integer)$input->getArgument('requiredAge');

    $consoleResponse = new ConsoleResponse($io);
    $this->eventExecutionService->setResponse($consoleResponse);
    $this->eventExecutionService->unlock($requiredAge);

    return Command::SUCCESS;
  }
}
