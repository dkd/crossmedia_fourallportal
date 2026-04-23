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

use Crossmedia\Fourallportal\Domain\Dto\SyncParameters;
use Crossmedia\Fourallportal\Queue\Message\SynchronizeMessage;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SynchronizationHandler
{
    public function __construct(
        protected readonly EventExecutionService $eventExecutionService,
    ) {
    }

    public function __invoke(SynchronizeMessage $message): void
    {
        $parameters = new SyncParameters();
        $parameters->setExecute(false)
            ->setFullSync($message->full)
            ->setExclude($message->exclude)
            ->setModule($message->moduleName)
        ;
        $this->eventExecutionService->sync($parameters);
    }
}
