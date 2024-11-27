<?php

namespace Crossmedia\Fourallportal\Controller;

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

use Crossmedia\Fourallportal\Domain\Dto\SyncParameters;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Repository\EventRepository;
use Crossmedia\Fourallportal\Hook\EventExecutionHookInterface;
use Crossmedia\Fourallportal\Response\CollectingResponse;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use Crossmedia\Fourallportal\Service\LoggingService;
use Crossmedia\Fourallportal\Utility\ControllerUtility;
use Crossmedia\Fourallportal\ViewHelpers\NumberedPagination;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;

/**
 * EventController
 */
#[AsController]
final class EventController extends ActionController
{
  /**
   * @param EventRepository|null $eventRepository
   * @param EventExecutionService|null $eventExecutionService
   * @param LoggingService|null $loggingService
   * @param SyncParameters|null $syncParameters
   * @param ModuleTemplateFactory $moduleTemplateFactory
   */
  public function __construct(
    protected ?EventRepository       $eventRepository,
    protected ?EventExecutionService $eventExecutionService,
    protected ?LoggingService        $loggingService,
    protected ?SyncParameters        $syncParameters,
    protected ModuleTemplateFactory  $moduleTemplateFactory)
  {
  }

  /**
   * action index
   *
   * @param string|null $status
   * @param string|null $search
   * @param string|null $objectId
   * @param Event|null $modifiedEvent
   * @return ResponseInterface
   * @throws InvalidQueryException
   * @IgnoreValidation("modifiedEvent")
   */
  public function indexAction(string $status = null, string $search = null, string $objectId = null, ?Event $modifiedEvent = null, int $currentPage = 1): ResponseInterface
  {
    $eventOptions = [
      'pending' => 'Status: pending',
      'failed' => 'Status: failed',
      'deferred' => 'Status: deferred',
      'claimed' => 'Status: claimed',
      'all' => 'Status: all'
    ];

    $searchWidened = null;
    if ($objectId) {
      $events = $this->eventRepository->findByObjectId($objectId);
      $status = 'all';
    } elseif ($status || $search) {
      // Load events with selected status
      $events = $this->searchEventsWithStatus($status, $search);
      if ($status !== 'all' && $events->count() === 0) {
        // Widen search to search other statuses than the selected one.
        $searchWidened = true;
        $events = $this->searchEventsWithStatus(false, $search);
        $status = 'all';
      }
    } else {
      // Find first status from prioritised list above which yields results
      do {
        $status = key($eventOptions);
        $events = $this->searchEventsWithStatus($status, $search);
      } while ($events->count() === 0 && next($eventOptions));
    }
    $view = $this->moduleTemplateFactory->create($this->request);
    // pagination$events
    $paginator = new QueryResultPaginator($events ?? null, $currentPage, 50);
    $pagination = new NumberedPagination($paginator, 10);

    // create header menu
    ControllerUtility::addMainMenu($this->request, $this->uriBuilder, $view, 'Event');
    // assign values
    $view->assignMultiple([
      'searchWidened' => $searchWidened,
      'status' => $status,
      'events' => $events,
      'search' => $search,
      'objectId' => $objectId,
      'modifiedEvent' => $modifiedEvent,
      'eventStatusOptions' => $eventOptions,
      'paginator' => $paginator,
      'pagination' => $pagination,
    ]);
    return $view->renderResponse('Event/Index');
  }

  /**
   * @param Event $event
   * @return ResponseInterface
   * @IgnoreValidation("event")
   */
  public function checkAction(Event $event): ResponseInterface
  {
    $view = $this->moduleTemplateFactory->create($this->request);
    // create header menu
    ControllerUtility::addMainMenu($this->request, $this->uriBuilder, $view, 'Event');
    $events = $this->eventRepository->findByObjectId($event->getObjectId());

    $view->assign('event', $event);
    $view->assign('event_json', 'event json value equals, traa: ' . json_encode($event));
    $view->assign('events', $events);
    $view->assign('eventLog', $this->loggingService->getEventActivity($event, 20));
    $view->assign('objectLog', $this->loggingService->getObjectActivity($event->getObjectId(), 100));
    foreach ($events as $historicalEvent) {
      if ($historicalEvent->getEventType() === 'delete') {
        $view->assign('deleted', ($historicalEvent->getStatus() === 'claimed'));
        $view->assign('deletedScheduled', ($historicalEvent->getStatus() === 'pending'));
        break;
      }
    }
    return $view->renderResponse('Event/Check');
  }

  /**
   * @param Event $event
   * @return ResponseInterface
   * @throws IllegalObjectTypeException
   * @throws UnknownObjectException
   */
  public function resetAction(Event $event): ResponseInterface
  {
    $event->setStatus('pending');
    $event->setNextRetry(0);
    $event->setRetries(0);
    $this->eventRepository->update($event);
    $this->loggingService->logEventActivity($event, 'Event reset');
    return $this->redirect('index', null, null, ['status' => 'pending']);
  }

  /**
   * @param Event $event
   * @return ResponseInterface
   * @throws Exception
   */
  public function executeAction(Event $event): ResponseInterface
  {
    $fakeResponse = new CollectingResponse();
    $this->eventExecutionService->setResponse($fakeResponse);
    $this->eventExecutionService->processEvent($event, false);

    $message = $fakeResponse->getCollected() ?: 'No output from action';

    /* Any hooks for post-execution processing */
    if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fourallportal']['postEventExecution'] ?? null)) {
      foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fourallportal']['postEventExecution'] as $postExecutionHookClass) {
        /** @var EventExecutionHookInterface $postExecutionHookInstance */
        $postExecutionHookInstance = GeneralUtility::makeInstance($postExecutionHookClass);
        $postExecutionHookInstance->postSingleManualEventExecution($event);
      }
    }

    $this->addFlashMessage($message, 'Executed event ' . $event->getEventId());
    return $this->redirect('index', null, null, ['modifiedEvent' => $event->getUid()]);
  }

  /**
   * @return ResponseInterface
   * @throws Exception
   * @throws IllegalObjectTypeException
   * @throws InvalidQueryException
   * @throws UnknownObjectException
   */
  public function syncAction(): ResponseInterface
  {
    $syncParameters = $this->syncParameters->setSync(true)->setExecute(false);
    $fakeResponse = new CollectingResponse();
    $this->eventExecutionService->setResponse($fakeResponse);
    $this->eventExecutionService->sync($syncParameters);
    $this->addFlashMessage(nl2br($fakeResponse->getCollected()) ?: 'No new events to fetch', 'Executed');
    return $this->redirect('index');
  }

  /**
   * @param string $status
   * @param string|null $search
   * @return QueryResultInterface
   * @throws InvalidQueryException
   */
  protected function searchEventsWithStatus(string $status, string|null $search): QueryResultInterface
  {
    $query = $this->eventRepository->createQuery();
    $constraints = null;
    if ($status !== 'all') {
      $constraints = $query->equals('status', $status);
    }

    if ($search) {
      $constraints = $query->logicalOr(
        $query->equals('eventId', (integer)$search),
        $query->like('module.connectorName', '%' . $search . '%'),
        $query->like('objectId', '%' . $search . '%'),
        $query->like('eventType', '%' . $search . '%'),
      );
    }

    if ($constraints) {
      $query->matching($query->logicalAnd($constraints));
    }

    $query->setOrderings(['crdate' => 'ASC']);
    return $query->execute();
  }
}
