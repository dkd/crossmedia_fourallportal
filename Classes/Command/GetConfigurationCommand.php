<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\Error\ApiException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'fourallportal:getConfiguration',
  description: 'Get module and connector configuration',
)]
class GetConfigurationCommand extends Command
{

  public function __construct(
    protected ?ServerRepository $serverRepository = null
  )
  {
    parent::__construct();
  }

  protected function configure()
  {
    $this
      ->setDescription('Get module and connector configuration')
      ->setHelp('Gets the module and connector configuration for the module identified by $moduleName, and outputs it as JSON.')
      ->addArgument('moduleName', InputArgument::REQUIRED, 'Name of module for which to get configuration')
      ->addArgument('server', InputArgument::OPTIONAL, 'Optional UID of server, defaults to active server', 0);
  }


  /**
   * Get module and connector configuration
   *
   * Gets the module and connector configuration for the module
   * identified by $moduleName, and outputs it as JSON.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   * @throws ApiException
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());

    $moduleName = (string)$input->getArgument('moduleName');
    $serverId = (int)$input->getArgument('server');

    if ($serverId === 0) {
      /** @var Server $server */
      $server = $this->serverRepository->findOneBy(['active' => true]);
    } else {
      /** @var Server $server */
      $server = $this->serverRepository->findByUid($serverId);
    }
    $module = $server->getModule($moduleName);
    $io->writeln(json_encode($module->getModuleConfiguration(), JSON_PRETTY_PRINT));
    $io->writeln(PHP_EOL);
    $io->writeln(json_encode($module->getConnectorConfiguration(), JSON_PRETTY_PRINT));

    return Command::SUCCESS;
  }

}