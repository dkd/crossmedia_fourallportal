<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\ConfigGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
      ->setHelp("Generates all configuration\n\n Shortcut method for calling all of the three specific\n generate commands to generate static configuration files for\n all dynamic-model-enabled modules' entities.")
      ->addArgument('entityClassName', InputArgument::REQUIRED, 'Name of the entity class. Use two back slashes on the command line')
      ->addArgument('strict', InputArgument::OPTIONAL, 'If TRUE, generates strict PHP code', false)
      ->addArgument('readOnly', InputArgument::OPTIONAL, 'If TRUE, generates TCA fields as read-only', false);
  }

  /**
   * Generates all configuration
   *
   * Shortcut method for calling all the three specific
   * generate commands to generate static configuration files for
   * all dynamic-model-enabled modules' entities.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   * @return int
   * @throws ApiException
   * @throws Exception
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());

    $entityClassName = (string)$input->getArgument('entityClassName');
    $strict = (bool)$input->getArgument('strict');
    $readOnly = (bool)$input->getArgument('readOnly');

    $this->configGeneratorService->setOutputInterface($output);
    $this->configGeneratorService->generateSqlSchemaCommand();
    $this->configGeneratorService->generateTableConfiguration($entityClassName, $readOnly);
    $this->configGeneratorService->generateAbstractModelClassCommand($io, $entityClassName, $strict);

    return Command::SUCCESS;
  }

}
