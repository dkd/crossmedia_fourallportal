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

namespace Crossmedia\Fourallportal\Domain\Dto;

readonly class Parameters
{
    public function __construct(
        public bool $sync = true,
        public bool $fullSync = false,
        public bool $force = false,
        public bool $execute = true,
        public ?string $module = null,
        public array $exclude = [],
        public int $timeLimit = 0,
        public int $eventLimit = 0,
        public int $beganTime = 0,
        public int $eventsExecuted = 0,
        public bool $deferredEvents = true,
        public bool $dropReleations = false
    ) {
    }
}
