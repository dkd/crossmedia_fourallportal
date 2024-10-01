<?php

namespace Crossmedia\Fourallportal\Tests\Service;

use Crossmedia\Fourallportal\Domain\Repository\EventRepository;
use Crossmedia\Fourallportal\Domain\Repository\ModuleRepository;
use Crossmedia\Fourallportal\Domain\Repository\ServerRepository;
use Crossmedia\Fourallportal\Service\EventExecutionService;
use Crossmedia\Fourallportal\Service\LoggingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Locking\Exception as LockingException;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireException;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class EventExecutionServiceTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSingletonInstances = true;
    }

    public static function lockDataProvider(): \Traversable
    {
        yield "Lock can be acquired, method will return true" => [
            'acquireResult' => true,
            'exception' => null,
            'expectedResult' => true,
        ];
        yield "Lock can not be acquired, method will return false" => [
            'acquireResult' => false,
            'exception' => null,
            'expectedResult' => false,
        ];
        yield "Acquire throws exception, method will return false" => [
            'acquireResult' => false,
            'exception' => new LockAcquireException(),
            'expectedResult' => false,
        ];
    }

    #[Test]
    #[DataProvider('lockDataProvider')]
    public function verifyLockBehaviour(bool $acquireResult, LockingException|null $exception, bool $expectedResult): void
    {
        $serverRepositoryMock = $this->createMock(ServerRepository::class);
        $eventRepositoryMock = $this->createMock(EventRepository::class);
        $moduleRepositoryMock = $this->createMock(ModuleRepository::class);
        $loggingServiceMock = $this->createMock(LoggingService::class);
        $persistenceManagerInterfaceMock = $this->createMock(PersistenceManagerInterface::class);
        $connectionPoolMock = $this->createMock(ConnectionPool::class);
        $schedulerTaskRepositoryMock = $this->createMock(SchedulerTaskRepository::class);
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $lockerMock = $this->createMock(LockingStrategyInterface::class);
        if ($exception !== null) {
            $lockerMock->expects(self::once())
                ->method('acquire')
                ->with(LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE)
                ->willThrowException($exception);
        } else {
            $lockerMock->expects(self::once())
                ->method('acquire')
                ->with(LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE)
                ->willReturn($acquireResult);
        }
        $lockFactoryMock = $this->createMock(LockFactory::class);
        $lockFactoryMock->expects(self::once())
            ->method('createLocker')
            ->with('4ap_sync', LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE)
            ->willReturn($lockerMock);
        GeneralUtility::setSingletonInstance(LockFactory::class, $lockFactoryMock);

        $eventExecutionService = new EventExecutionService(
            $serverRepositoryMock,
            $eventRepositoryMock,
            $moduleRepositoryMock,
            $loggingServiceMock,
            $persistenceManagerInterfaceMock,
            $connectionPoolMock,
            $schedulerTaskRepositoryMock,
            $extensionConfigurationMock,
        );

        self::assertEquals(
            $expectedResult,
            $eventExecutionService->lock()
        );
    }

    public static function unlockDataProvider(): \Traversable
    {
        yield "Lock can be released, method will return true" => [
            'released' => true,
            'exception' => null,
            'expectedResult' => true,
        ];
        yield "Lock can not be released, method will return false" => [
            'released' => false,
            'exception' => null,
            'expectedResult' => false,
        ];
        yield "Release the lock will throws exception, method will return false" => [
            'released' => false,
            'exception' => new LockingException(),
            'expectedResult' => false,
        ];
    }

    #[Test]
    #[DataProvider('unlockDataProvider')]
    public function verifyUnLockBehaviour(bool $released, LockingException|null $exception, bool $expectedResult): void
    {
        $serverRepositoryMock = $this->createMock(ServerRepository::class);
        $eventRepositoryMock = $this->createMock(EventRepository::class);
        $moduleRepositoryMock = $this->createMock(ModuleRepository::class);
        $loggingServiceMock = $this->createMock(LoggingService::class);
        $persistenceManagerInterfaceMock = $this->createMock(PersistenceManagerInterface::class);
        $connectionPoolMock = $this->createMock(ConnectionPool::class);
        $schedulerTaskRepositoryMock = $this->createMock(SchedulerTaskRepository::class);
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        $lockerMock = $this->createMock(LockingStrategyInterface::class);
        if ($exception !== null) {
            $lockerMock->expects(self::once())
                ->method('release')
                ->willThrowException($exception);
        } else {
            $lockerMock->expects(self::once())
                ->method('release')
                ->willReturn($released);
        }

        $lockFactoryMock = $this->createMock(LockFactory::class);
        $lockFactoryMock->expects(self::once())
            ->method('createLocker')
            ->with('4ap_sync', LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE)
            ->willReturn($lockerMock);
        GeneralUtility::setSingletonInstance(LockFactory::class, $lockFactoryMock);

        $eventExecutionService = new EventExecutionService(
            $serverRepositoryMock,
            $eventRepositoryMock,
            $moduleRepositoryMock,
            $loggingServiceMock,
            $persistenceManagerInterfaceMock,
            $connectionPoolMock,
            $schedulerTaskRepositoryMock,
            $extensionConfigurationMock,
        );

        self::assertEquals(
            $expectedResult,
            $eventExecutionService->unlock()
        );
    }
}
