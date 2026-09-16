<?php

namespace Crossmedia\Fourallportal\Controller;

use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Model\Server;
use Crossmedia\Fourallportal\Domain\Repository\EventRepository;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\Error\ApiException;
use Crossmedia\Fourallportal\Service\LoggingService;
use Crossmedia\Fourallportal\Utility\ControllerUtility;
use PHPUnit\Exception;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;

/***
 *
 * This file is part of the "4AllPortal Connector" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Marc Neuhaus <marc@mia3.com>, MIA3 GmbH & Co. KG
 *
 ***/

/**
 * ServerController
 */
#[AsController]
final class ServerController extends ActionController
{

  /**
   * @param ServerRepository|null $serverRepository
   * @param EventRepository|null $eventRepository
   * @param LoggingService|null $loggingService
   * @param ModuleRepository|null $moduleRepository
   * @param ModuleTemplateFactory $moduleTemplateFactory
   */
  public function __construct(
    protected ?ServerRepository     $serverRepository,
    protected ?EventRepository      $eventRepository,
    protected ?LoggingService       $loggingService,
    protected ?ModuleRepository     $moduleRepository,
    protected ModuleTemplateFactory $moduleTemplateFactory)
  {
  }


  /**
   * action index
   * @return ResponseInterface
   */
  public function indexAction(): ResponseInterface
  {
    $view = $this->moduleTemplateFactory->create($this->request);
    // create header menu
    ControllerUtility::addMainMenu($this->request, $this->uriBuilder, $view, 'Server');
    // assign values
    $view->assign('servers', $this->serverRepository->findAll());
    return $view->renderResponse('Server/Index');
  }

  /**
   * @param Module|null $module
   * @param string|null $uuid
   * @return ResponseInterface
   * @throws ApiException
   */
  public function moduleAction(Module $module = null, string $uuid = null): ResponseInterface
  {
    // create header menu
    $view = $this->moduleTemplateFactory->create($this->request);
    ControllerUtility::addMainMenu($this->request, $this->uriBuilder, $view, 'Server');
    if ($module) {
      $response = $module->getServer()->getClient()->getBeans($uuid, $module->getConnectorName());
      $pretty = json_encode($response, JSON_PRETTY_PRINT);
      $view->assign('prettyResponse', $pretty);
      $view->assign('response', $response);
      $view->assign('uuid', $uuid);
      $view->assign('module', $module);
      $view->assign('verifyRelations', true);
    }
    $view->assign('modules', $this->moduleRepository->findAll());
//    return new HtmlResponse($view->render());
    return $view->renderResponse('Server/Module');

  }

  /**
   * action check
   * @param Server $server
   * @return ResponseInterface
   * @throws ApiException
   * */
  #[IgnoreValidation(['argumentName' => 'server'])]
  public function checkAction(Server $server): ResponseInterface
  {
    $status = [
      'title' => 'login',
      'class' => 'info',
      'description' => 'starting progress',
    ];
    try {
      $client = $server->getClient();
      $sessionId = $client->login();
      $status[] = [
        'title' => 'login',
        'class' => $sessionId ? 'success' : 'danger',
        'description' => $sessionId ? 'Login Successful. Session ID: ' . $sessionId : 'Login failed',
      ];
      foreach ($server->getModules() as $module) {
//        /** @var Module $module */
        try {
          $connectorName = $module->getConnectorName();
          $config = $client->getConnectorConfig($connectorName);
          $currentConfigurationHash = $module->getConfigHash();

          $mapper = $module->getMapper();
          if ($config['config_hash'] !== $currentConfigurationHash) {
            $description = '
                            <h2 class="text-danger">WARNING</h2>
                            <p>The config hash "' . $config['config_hash'] . '" does not match the persisted config hash
                            "' . $currentConfigurationHash . '" which indicates the PIM schema has changed. To update
                            compatibility you can use the CLI command <code>fourallportal:pinschema</code>.
                        ';
          } else {
            $description = '
                            <strong>Module Name:</strong> ' . $config['moduleConfig']['module_name'] . '<br />
                            <strong>Config Hash:</strong> ' . $config['config_hash'] . '<br />
                            <strong>Entity class:</strong> ' . ($mapper !== null) ? $module->getMapper()->getEntityClassName() : 'not found!' . '<br />
                            <strong>Test object UUID:</strong> ' . $module->getTestObjectUuid() . '<br />
                        ';
          }

          $moduleStatus = [
            'title' => 'connector: ' . $connectorName,
            'description' => $description,
          ];

          $mappingClass = $module->getMappingClass();
          $mapping = new $mappingClass();
          $moduleStatus = $mapping->check($client, $module, $moduleStatus);
          $status[] = $moduleStatus;
        } catch (ApiException $exception) {
          $status[] = [
            'title' => 'connector: ' . $module->getConnectorName(),
            'class' => 'danger',
            'description' => $exception->getMessage(),
          ];
        }
      }
    } catch (\Exception $exception) {
      $status[] = [
        'title' => 'login',
        'class' => 'danger',
        'description' => 'Login failed (' . $exception->getMessage() . ')',
      ];
    }
    $view = $this->moduleTemplateFactory->create($this->request);
    // create header menu
    ControllerUtility::addMainMenu($this->request, $this->uriBuilder, $view, 'Server');
    // assign values
    $view->assign('servers', $this->serverRepository->findAll());
    $view->assign('connectionLog', $this->loggingService->getConnectionActivity(200));
    $view->assign('errorLog', $this->loggingService->getErrorActivity(200));
    $view->assign('status', $status);
    $view->assign('server', $server);
    return $view->renderResponse('Server/Check');
  }

  /**
   * action disable
   *
   * @param Server $server
   * @return ResponseInterface
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function disableAction(Server $server): ResponseInterface
  {
    $server->setActive(false);
    $this->serverRepository->update($server);
    return $this->redirect('index');
  }

  /**
   * action enable
   *
   * @param Server $server
   * @return ResponseInterface
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function enableAction(Server $server): ResponseInterface
  {
    foreach ($this->serverRepository->findAll() as $persistedServer) {
      $persistedServer->setActive($server === $persistedServer);
      $this->serverRepository->update($persistedServer);
    }

    return $this->redirect('index');
  }

  /**
   * action delete
   *
   * @param Server $server
   * @return ResponseInterface
   * @throws IllegalObjectTypeException
   */
  public function deleteAction(Server $server): ResponseInterface
  {
    $this->serverRepository->remove($server);
    return $this->redirect('index');
  }

  /**
   * action restartSynchronisation
   *
   * @param Server $server
   * @return ResponseInterface
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function restartSynchronisationAction(Server $server): ResponseInterface
  {
    foreach ($server->getModules() as $module) {
      $module->setLastEventId(0);
      $events = $this->eventRepository->findByModule($module);
      foreach ($events as $event) {
        $this->eventRepository->remove($event);
      }
    }
    $this->serverRepository->update($server);
    return $this->redirect('index');
  }

}
