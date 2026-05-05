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

namespace Crossmedia\Fourallportal\Queue\Handler;

use Crossmedia\Fourallportal\Domain\Enum\EventStatus;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\EventRepository;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Hook\EventExecutionHookInterface;
use Crossmedia\Fourallportal\Queue\Message\EventExecuteMessage;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use Crossmedia\Fourallportal\Service\LoggingService;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class EventExecutionHandler
{
    public function __construct(
        private readonly EventExecutionService $eventExecutionService,
        private readonly ModuleRepository $moduleRepository,
        private readonly EventRepository $eventRepository,
        private readonly LoggingService $loggingService,
        private readonly PersistenceManager $manager,
    ) {
    }

    public function __invoke(EventExecuteMessage $message): void
    {
        if (empty($message->moduleName)) {
            return;
        }
        $module = $this->moduleRepository->findOneBy(['moduleName' => $message->moduleName]);
        if (!($module instanceof Module)) {
            return;
        }
        $event = $this->eventRepository->findOneByModuleAndEventId(
            $module,
            $message->eventId
        );
        if (!($event instanceof Event)) {
            return;
        }
        try {
            $this->loggingService->logEventActivity($event, '[Queued event] Begin processing');
            $event->setProcessing(false);
            $success = $this->eventExecutionService->processEvent($event, false);
            if ($success === false) {
                $this->updateEvent(
                    $event,
                    null, // Keep status as is
                    '[Queued event] Execution not successful (see event/object log for more information)',
                    LogLevel::WARNING
                );
            } else {
                $this->loggingService->logEventActivity($event, '[Queued event] Execution successful');
            }
            /* Any hooks for post-execution processing */
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fourallportal']['postEventExecution'] ?? null)) {
                foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fourallportal']['postEventExecution'] as $postExecutionHookClass) {
                    try {
                        /** @var EventExecutionHookInterface $postExecutionHookInstance */
                        $postExecutionHookInstance = GeneralUtility::makeInstance($postExecutionHookClass);
                        $postExecutionHookInstance->postSingleManualEventExecution($event);
                    } catch (\Throwable $throwable) {
                        $this->loggingService->logEventActivity(
                            $event,
                            '[Queued event] Post processing by ' . $postExecutionHookClass . ' failed',
                            severity: LogLevel::ERROR
                        );
                    }
                }
            }
        } catch (\Throwable $throwable) {
            $this->updateEvent(
                $event,
                EventStatus::Failed,
                '[Queued event] Execution failed with ' . $throwable->getMessage(),
                LogLevel::ERROR
            );
        }
    }

    /**
     * Update event and write event log message
     *
     * @param Event $event
     * @param EventStatus|null $status
     * @param string $logMessage
     * @param string $severity
     * @return void
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    protected function updateEvent(
        Event $event,
        EventStatus|null $status,
        string $logMessage,
        string $severity = LogLevel::INFO
    ): void {
        $this->loggingService->logEventActivity($event, $logMessage, $severity);
        $event->setProcessing(false);
        if ($status !== null) {
            $event->setStatus($status->value);
        }
        $this->eventRepository->update($event);
        $this->manager->persistAll();
    }
}
