<?php

namespace Crossmedia\Fourallportal\Command;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

#[AsCommand(
  name: 'fourallportal:initialize'
)]
class InitializeCommand extends Command
{

  public function __construct(
    protected ?ModuleRepository   $moduleRepository = null,
    protected ?PersistenceManager $persistenceManager = null,
    protected ?ServerRepository   $serverRepository = null,
  )
  {
    parent::__construct();
  }

  /**
   * Configure the command by defining the name, options and arguments
   */
  protected function configure()
  {
    $this
      ->setDescription('Creates Server and Module configuration if configured in extension configuration. The array in:  $GLOBALS[\'TYPO3_CONF_VARS\'][\'EXT\'][\'extConf\'][\'fourallportal\']')
      ->addArgument(
        'fail',
        InputArgument::OPTIONAL,
        'If TRUE, any connectivity test failure will cause the command to exit with failure'
      );
  }

  /**
   * Initialize system
   *
   * Creates Server and Module configuration if configured in
   * extension configuration. The array in:
   *
   * $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal']
   *
   * can contain an array of servers and modules, e.g.:
   *
   * · [
   * ·   'default' => [
   * ·     'domain' => '',
   * ·     'customerName' => '',
   * ·     'username' => '',
   * ·     'password' => '',
   * ·     'active' => 1,
   * ·     'modules' => [
   * ·       'module_name' => [
   * ·         'connectorName' => '',
   * ·         'mappingClass' => '',
   * ·         'shellPath' => '',
   * ·         'falStorage' => '',
   * ·         'storagePid' => '',
   * ·       ],
   * ·     ],
   * ·   ],
   * · ]
   *
   * Note that the module properties may differ depending on which
   * mapping class the module uses, and that the server name does
   * not get used - it is only there to identify the entry in your
   * configuration file.
   *
   * @param bool $fail If TRUE, any connectivity test failure will cause the command to exit with failure.
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $io = new SymfonyStyle($input, $output);
    $io->title($this->getDescription());
    $fail = $input->getArgument('fail');

    $settings = GeneralUtility::removeDotsFromTS((array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal']));
    if (isset($settings['servers'])) {
      foreach ($settings['servers'] as $server) {
        $currentServer = $this->serverRepository->findOneByDomain($server['domain']);
        if (!$currentServer) {
          $content .= 'Creating new server for ' . $server['domain'] . PHP_EOL;
          $currentServer = new Server();
          $this->serverRepository->add($currentServer);
        } else {
          $content .= 'Updating configuration for ' . $server['domain'] . PHP_EOL;
          $this->serverRepository->update($currentServer);
        }

        if ($server['username']) {
          $currentServer->setUsername($server['username']);
          $content .= '* Username: ' . $server['username'] . PHP_EOL;
        }

        if ($server['password']) {
          $currentServer->setPassword($server['password']);
          $content .= '* Password: ' . $server['password'] . PHP_EOL;
        }

        $currentServer->setActive((bool)$server['active']);
        $content .= '* Active: ' . $server['active'] . PHP_EOL;

        $currentServer->setCustomerName($server['customerName']);
        $content .= '* Customer name: ' . $server['customerName'] . PHP_EOL;

        $currentServer->setDomain($server['domain']);

        $content .= '* Testing connectivity... ';
        $io->write($content);
        $content = '';

        try {
          $currentServer->getClient()->login();
          $content .= 'OKAY!';
        } catch (Exception $error) {
          $content .= 'ERROR! ' . $error->getMessage();
          if ($fail) {
            $io->write($content);
            return Command::FAILURE;
          }
        }
        $content .= PHP_EOL;

        foreach ($server['modules'] as $moduleName => $moduleProperties) {
          $module = $this->ensureServerHasModule($content, $currentServer, $moduleName, $moduleProperties);
          $module->setServer($currentServer);

          $content .= '* Testing connectivity... ';
          $io->write($content);
          $content = '';

          try {
            $module->getModuleConfiguration();
            $content .= 'OKAY!';
          } catch (Exception $error) {
            $content .= 'ERROR! ' . $error->getMessage();
            if ($fail) {
              $io->write($content);
              return Command::FAILURE;
            }
          }
          $content .= PHP_EOL;
        }
      }
    }
    $this->persistenceManager->persistAll();
    return Command::SUCCESS;
  }

  /**
   * @param string $content
   * @param Server $server
   * @param string $moduleName
   * @param array $moduleProperties
   * @return Module
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  protected function ensureServerHasModule(string $content, Server $server, $moduleName, array $moduleProperties)
  {
    $currentModule = new Module();
    $foundModule = false;
    foreach ($server->getModules() as $module) {
      if ($module->getModuleName() === $moduleName) {
        $foundModule = true;
        $currentModule = $module;
        break;
      }
    }
    if (!$foundModule) {
      $content .= 'Adding new module for ' . $moduleName . PHP_EOL;
    } else {
      $content .= 'Updating existing module configuration for ' . $moduleName . PHP_EOL;
    }

    $currentModule->setModuleName($moduleName);

    $currentModule->setConnectorName($moduleProperties['connectorName']);
    $content .= '* Connector name: ' . $moduleProperties['connectorName'] . PHP_EOL;

    $currentModule->setMappingClass($moduleProperties['mappingClass']);
    $content .= '* Mapping class: ' . $moduleProperties['mappingClass'] . PHP_EOL;

    $currentModule->setEnableDynamicModel($moduleProperties['enableDynamicModel'] ?? false);
    $content .= '* Dynamic: ' . $moduleProperties['enableDynamicModel'] . PHP_EOL;

    if ($moduleProperties['shellPath'] ?? false) {
      $currentModule->setShellPath($moduleProperties['shellPath']);
      $content .= '* Shell path: ' . $moduleProperties['shellPath'] . PHP_EOL;
    }

    if ($moduleProperties['falStorage'] ?? false) {
      $currentModule->setFalStorage((int)$moduleProperties['falStorage']);
      $content .= '* FAL storage: ' . $moduleProperties['falStorage'] . PHP_EOL;
    }

    if ($moduleProperties['storagePid'] ?? false) {
      $currentModule->setStoragePid((int)$moduleProperties['storagePid']);
      $content .= '* Storage PID: ' . $moduleProperties['storagePid'] . PHP_EOL;
    }

    if ($currentModule->getUid()) {
      $this->moduleRepository->update($currentModule);
    } else {
      $this->moduleRepository->add($currentModule);
      $server->getModules()->attach($currentModule);
    }
    return $currentModule;
  }
}