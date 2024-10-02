<?php

namespace Crossmedia\Fourallportal\Domain\Repository;

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
      $query->equals('status', 'deferred'),
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
     * @param string $status
     * @param string|null $search
     * @return QueryResultInterface
     * @throws InvalidQueryException
     */
    public function searchEventsWithStatus(string $status, string|null $search): QueryResultInterface
    {
        $query = $this->createQuery();
        $constraints = null;

        if ($search) {
            $constraints = $query->logicalOr(
                $query->equals('eventId', (integer)$search),
                $query->like('module.connectorName', '%' . $search . '%'),
                $query->like('objectId', '%' . $search . '%'),
                $query->like('eventType', '%' . $search . '%'),
            );
        } elseif ($status !== 'all') {
            $constraints = $query->equals('status', $status);
        }

        if ($constraints) {
            $query->matching($query->logicalAnd($constraints));
        }

        $query->setOrderings(['crdate' => 'ASC']);
        return $query->execute();
    }
}
