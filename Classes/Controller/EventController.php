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

use Crossmedia\Fourallportal\Domain\Enum\EventStatus;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Repository\EventRepository;
use Crossmedia\Fourallportal\Queue\Message\EventExecuteMessage;
use Crossmedia\Fourallportal\Queue\Message\SynchronizeMessage;
use Crossmedia\Fourallportal\Service\LoggingService;
use Crossmedia\Fourallportal\Utility\ControllerUtility;
use Crossmedia\Fourallportal\ViewHelpers\NumberedPagination;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;

/**
 * EventController
 */
#[AsController]
final class EventController extends ActionController
{
    private const array RETURN_TO_PARAMETERS = [
        'status',
        'search',
        'objectId',
        'currentPage',
        'event'
    ];

    /**
     * @param EventRepository $eventRepository
     * @param LoggingService $loggingService
     * @param ModuleTemplateFactory $moduleTemplateFactory
     */
    public function __construct(
        protected EventRepository $eventRepository,
        protected LoggingService $loggingService,
        protected ModuleTemplateFactory $moduleTemplateFactory,
        private readonly MessageBusInterface $bus,
        private readonly PersistenceManager $manager,
        protected readonly IconFactory $iconFactory,
    ) {
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
     */
    #[IgnoreValidation(['argumentName' => 'modifiedEvent'])]
    public function indexAction(
        string $status = null,
        string $search = null,
        string $objectId = null,
        ?Event $modifiedEvent = null,
        int $currentPage = 1
    ): ResponseInterface {
        $eventOptions = [];
        foreach (EventStatus::cases() as $case) {
            $eventOptions[$case->value] =  'Status: ' . $case->name;
        }

        $eventOptions['processing'] = 'Currently processing';
        $eventOptions['all'] = 'All';

        $searchWidened = null;
        if ($objectId) {
            $objectId = trim($objectId);
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
        $this->makeButtons(
            $view,
            'index',
            [
                'status' => $status,
                'search' => $search,
                'objectId' => $objectId,
                'currentPage' => $currentPage,
            ]
        );
        // pagination$events
        $paginator = new QueryResultPaginator($events ?? null, $currentPage, 50);
        $pagination = new NumberedPagination($paginator, 10);

        // create header menu
        ControllerUtility::addMainMenu($this->request, $this->uriBuilder, $view, 'Event');
        $returnTo = [
            'action' => 'index',
            'status' => $status,
            'search' => $search,
            'objectId' => $objectId,
            'currentPage' => $currentPage
        ];
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
            'returnTo' => $returnTo
        ]);
        return $view->renderResponse('Event/Index');
    }

    /**
     * @param Event $event
     * @return ResponseInterface
     */
    #[IgnoreValidation(['argumentName' => 'event'])]
    public function checkAction(Event $event): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($this->request);
        $this->makeActionButtons($view);
        $this->makeButtons(
            $view,
            'check',
            [
                'event' => $event->getUid()
            ]
        );
        // create header menu
        ControllerUtility::addMainMenu($this->request, $this->uriBuilder, $view, 'Event');
        $events = $this->eventRepository->findByObjectId($event->getObjectId());
        $returnTo = [
            'action' => 'check',
            'event' => $event->getUid()
        ];
        $view->assignMultiple(
            [
                'event' => $event,
                'event_json' => 'event json value equals, traa: ' . json_encode($event),
                'events' => $events,
                'eventLog' => $this->loggingService->getEventActivity($event, 20),
                'objectLog' => $this->loggingService->getObjectActivity($event->getObjectId(), 100),
                'returnTo' => $returnTo
            ]
        );
        foreach ($events as $historicalEvent) {
            if ($historicalEvent->getEventType() === 'delete') {
                $view->assign('deleted', ($historicalEvent->getStatus() === EventStatus::Claimed->value));
                $view->assign('deletedScheduled', ($historicalEvent->getStatus() === EventStatus::Pending->value));
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
        $event->reset();
        $this->eventRepository->update($event);
        $this->manager->persistAll();
        $this->loggingService->logEventActivity($event, 'Event reset');

        $returnTo = $this->request->getQueryParams()['returnTo'] ?? [];
        $action = $returnTo['action'] ?? 'index';
        $arguments = [
            'status' => EventStatus::Pending->value
        ];

        foreach ($returnTo as $fieldName => $value) {
            if (!in_array($fieldName, self::RETURN_TO_PARAMETERS)) {
                continue;
            }
            $arguments[$fieldName] = $value;
        }

        return $this->redirect(
            $action,
            null,
            null,
            $arguments
        );
    }

    /**
     * @param Event $event
     * @return ResponseInterface
     * @throws Exception
     */
    public function executeAction(Event $event): ResponseInterface
    {
        $this->eventRepository->queueEvent($event);
        $this->manager->persistAll();
        $this->loggingService->logEventActivity($event, 'Event queued for execution');
        $message = new EventExecuteMessage(
            $event->getModule()?->getModuleName(),
            $event->getEventId()
        );
        $this->bus->dispatch($message);

        $this->addFlashMessage(
            vsprintf(
                'Event %1$s for object %2$s was queued for processing. Please be aware that this can take a while before you see changes.' . PHP_EOL .
                'To see the current state of the event, please filter the event list for the status "in Queue"',
                [
                    $event->getEventId(),
                    $event->getObjectId()
                ]
            ),
            'Event dispatched'
        );

        $returnTo = $this->request->getQueryParams()['returnTo'] ?? [];
        $action = $returnTo['action'] ?? 'index';
        $arguments = [
            'status' => 'queued',
            'modifiedEvent' => $event->getUid()
        ];

        foreach ($returnTo as $fieldName => $value) {
            if (!in_array($fieldName, self::RETURN_TO_PARAMETERS)) {
                continue;
            }
            $arguments[$fieldName] = $value;
        }

        return $this->redirect(
            $action,
            null,
            null,
            $arguments
        );
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
        $message = new SynchronizeMessage();
        $this->bus->dispatch($message);
        $this->addFlashMessage(
            'Synchronization was queued for processing.' . PHP_EOL .
            'Please be aware that this can take a while before the synchronization starts and is completed.',
            'Event dispatch'
        );
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

        if ($status === 'processing') {
            $constraints = $query->equals('processing', true);
        } elseif ($status !== 'all') {
            $constraints = $query->equals('status', $status);
        }

        if ($search) {
            $constraintsSearch = $query->logicalOr(
                $query->equals('eventId', (int)$search),
                $query->like('module.connectorName', '%' . $search . '%'),
                $query->like('objectId', '%' . $search . '%'),
                $query->like('eventType', '%' . $search . '%'),
            );
            if ($constraints === null) {
                $constraints = $constraintsSearch;
            } else {
                $constraints = $query->logicalAnd(
                    $constraints,
                    $constraintsSearch
                );
            }
        }

        if ($constraints) {
            $query->matching($query->logicalAnd($constraints));
        }

        $query->setOrderings(['crdate' => 'ASC']);
        return $query->execute();
    }

    /**
     * This creates the buttons for the modules
     */
    protected function makeActionButtons(ModuleTemplate $view): void
    {
        $languageService = $this->getLanguageService();
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();

        $indexUri = $this->uriBuilder
            ->setRequest($this->request)
            ->uriFor('index', [], 'Event');
        // Reload
        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($indexUri)
            ->setTitle('Close')
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-close', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton);
    }

    /**
     * This creates the buttons for the modules
     */
    protected function makeButtons(
        ModuleTemplate $view,
        string $action = 'index',
        array $arguments = []
    ): void {
        $languageService = $this->getLanguageService();
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();

        $reloadUri = $this->uriBuilder
            ->setRequest($this->request)
            ->uriFor($action, $arguments, 'Event');

        // Reload
        $reloadButton = $buttonBar->makeLinkButton()
            ->setHref($reloadUri)
            ->setTitle($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.reload'))
            ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($reloadButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
