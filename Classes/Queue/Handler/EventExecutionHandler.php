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

use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use Crossmedia\Fourallportal\Domain\Repository\EventRepository;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Hook\EventExecutionHookInterface;
use Crossmedia\Fourallportal\Queue\Message\EventExecuteMessage;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EventExecutionHandler
{
    public function __construct(
        protected readonly EventExecutionService $eventExecutionService,
        protected readonly ModuleRepository $moduleRepository,
        protected readonly EventRepository $eventRepository
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
        $this->eventExecutionService->processEvent($event, false);
        /* Any hooks for post-execution processing */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fourallportal']['postEventExecution'] ?? null)) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fourallportal']['postEventExecution'] as $postExecutionHookClass) {
                /** @var EventExecutionHookInterface $postExecutionHookInstance */
                $postExecutionHookInstance = GeneralUtility::makeInstance($postExecutionHookClass);
                $postExecutionHookInstance->postSingleManualEventExecution($event);
            }
        }
    }
}
