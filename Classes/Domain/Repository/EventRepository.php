<?php

namespace Crossmedia\Fourallportal\Domain\Repository;

use Crossmedia\Fourallportal\Domain\Enum\EventStatus;
use Crossmedia\Fourallportal\Domain\Model\Event;
use Crossmedia\Fourallportal\Domain\Model\Module;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

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
 * The repository for Events
 */
class EventRepository extends Repository
{

    /**
     * @param string $objectId
     * @param int $limit
     * @return array|QueryResultInterface
     */
    public function findByObjectId(string $objectId, int $limit = 0): array|QueryResultInterface
    {
        $query = $this->createQuery();
        $constraint = $query->logicalAnd(
            $query->equals('object_id', $objectId),
            $query->equals('module.server.active', true),
        );
        $query->matching($constraint);
        $query->setOrderings(['crdate' => 'ASC']);
        if ($limit) {
            $query->setLimit($limit);
        }
        return $query->execute();
    }

    /**
     * @param int $eventId
     * @return object|null
     */
    public function findByEventId(int $eventId): ?object
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('eventId', $eventId)
            )
        );
        return $query->execute()->getFirst();
    }

    /**
     * @param string $status
     * @param int $limit
     * @param bool $includeProcessing
     * @return array|QueryResultInterface
     */
    public function findByStatus(string $status, int $limit = 0, bool $includeProcessing = true): array|QueryResultInterface
    {
        $query = $this->createQuery();
        $constraints = [
            $query->equals('status', $status),
            $query->equals('module.server.active', true),
        ];
        if (!$includeProcessing) {
            $constraints[] = $query->logicalNot($query->equals('processing', true));
        }
        $constraint = $query->logicalAnd(...$constraints);

        $query->matching($constraint);
        $query->setOrderings(['crdate' => 'ASC']);
        if ($limit) {
            $query->setLimit($limit);
        }
        return $query->execute();
    }

    /**
     * @param int $limit
     * @return array|QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findDeferred(int $limit = 0): array|QueryResultInterface
    {
        $query = $this->createQuery();
        $constraint = $query->logicalAnd(
            $query->equals('status', EventStatus::Deferred->value),
            $query->lessThan('nextRetry', time()),
            $query->logicalNot($query->equals('processing', true))
        );
        $query->matching($constraint);
        $query->setOrderings(['crdate' => 'ASC']);
        if ($limit) {
            $query->setLimit($limit);
        }
        return $query->execute();
    }

    /**
     * @param Module $module
     * @param int $limit
     * @return QueryResultInterface|array
     */
    public function findByModule(Module $module, int $limit = 0): QueryResultInterface|array
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('module', $module->getUid()),
            )
        );
        if ($limit) {
            $query->setLimit($limit);
        }
        return $query->execute();
    }

    /**
     * @param Module $module
     * @param int $eventId
     * @return object|null
     */
    public function findOneByModuleAndEventId(Module $module, int $eventId): ?object
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('module', $module->getUid()),
                $query->equals('eventId', $eventId)
            )
        );
        return $query->execute()->getFirst();
    }

    /**
     * Mark an event as queued for asynchronous execution
     *
     * @param Event $event
     * @return void
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    public function queueEvent(Event $event): void
    {
        $event->reset();
        $event->setStatus(EventStatus::Queued->value);
        $this->update($event);
    }
}
