<?php

namespace Crossmedia\Fourallportal\Domain\Dto;

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
 * Parameters for executing a sync
 */
class SyncParameters
{
    protected bool $sync = true;
    protected bool $fullSync = false;
    protected bool $force = false;
    protected bool $execute = true;
    protected ?string $module = null;
    protected array $exclude = [];
    protected int $timeLimit = 0;
    protected int $eventLimit = 0;
    private int $beganTime = 0;
    private int $eventsExecuted = 0;
    private bool $deferredEvents = true;

    /**
     * Set this variable to true in order to rebuild all pim related relations
     * The deletion has to be handles within the customer extension.
     *
     * This should not be the default, since some tables uses UIDs to have a correct access.
     *
     * @var bool
     */
    private bool $dropReleations = false;

    /**
     * Creates an immutable data base that can be used for events.
     *
     * This prevents that parameter changes for example by an event listener
     *
     * @return Parameters
     */
    public function toImmutableDataBag(): Parameters
    {
        return new Parameters(
            $this->sync,
            $this->fullSync,
            $this->force,
            $this->execute,
            $this->module,
            $this->exclude,
            $this->timeLimit,
            $this->eventLimit,
            $this->beganTime,
            $this->eventsExecuted,
            $this->deferredEvents,
            $this->dropReleations,
        );
    }

    public function shouldContinue(): bool
    {
        if ($this->timeLimit > 0 && (time() - $this->beganTime) >= $this->timeLimit) {
            return false;
        }
        if ($this->eventLimit > 0 && $this->eventsExecuted >= $this->eventLimit) {
            return false;
        }
        return true;
    }

    public function startExecution(): self
    {
        $this->beganTime = time();
        return $this;
    }

    public function countExecutedEvent(): int
    {
        return ++$this->eventsExecuted;
    }

    public function getFullSync(): bool
    {
        return $this->fullSync;
    }

    public function setFullSync(bool $fullSync): self
    {
        $this->fullSync = $fullSync;
        return $this;
    }

    public function getSync(): bool
    {
        return $this->sync;
    }

    public function setSync(bool $sync): self
    {
        $this->sync = $sync;
        return $this;
    }

    public function getForce(): bool
    {
        return $this->force;
    }

    public function setForce(bool $force): self
    {
        $this->force = $force;
        return $this;
    }

    public function getExecute(): bool
    {
        return $this->execute;
    }

    public function setExecute(bool $execute): self
    {
        $this->execute = $execute;
        return $this;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule($module): self
    {
        $this->module = $module;
        return $this;
    }

    public function getExclude(): array
    {
        return $this->exclude;
    }

    public function setExclude(array|string|null $exclude): self
    {
        if ($exclude === null || $exclude === '') {
            $this->exclude = [];
        } else {
            $this->exclude = is_array($exclude) ? $exclude : explode(',', (string)$exclude);
        }
        return $this;
    }

    public function isModuleExcluded(string $moduleName): bool
    {
        return in_array($moduleName, (array)$this->exclude, true);
    }

    public function excludeModule(string $moduleName): self
    {
        if (!in_array($moduleName, $this->exclude, true)) {
            $this->exclude[] = $moduleName;
        }
        return $this;
    }

    public function getTimeLimit(): int
    {
        return $this->timeLimit;
    }

    public function setTimeLimit(int $timeLimit): self
    {
        $this->timeLimit = $timeLimit;
        return $this;
    }

    public function getEventLimit(): int
    {
        return $this->eventLimit;
    }

    public function setEventLimit(int $eventLimit): self
    {
        $this->eventLimit = $eventLimit;
        return $this;
    }

    public function withDeferredEvents(): self
    {
        $this->deferredEvents = true;
        return $this;
    }

    public function withoutDeferredEvents(): self
    {
        $this->deferredEvents = false;
        return $this;
    }

    public function processDeferredEvents(): bool
    {
        return $this->deferredEvents;
    }

    public function setDropAllRelations(bool $dropReleations): self
    {
        $this->dropReleations = $dropReleations;
        return $this;
    }
}
