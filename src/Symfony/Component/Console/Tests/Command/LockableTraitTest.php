<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockBundle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;

class LockableTraitTest extends TestCase
{
    protected static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/FooLockCommand.php';
        require_once self::$fixturesPath.'/FooLock2Command.php';
        require_once self::$fixturesPath.'/FooLock3Command.php';
        require_once self::$fixturesPath.'/FooLock4InvokableCommand.php';
        require_once self::$fixturesPath.'/FooLock5InvokableCommand.php';
        require_once self::$fixturesPath.'/FooLockAliasedInvokableCommand.php';
        require_once self::$fixturesPath.'/FooLockReleaseFailureCommand.php';
        require_once self::$fixturesPath.'/FooLockMethodsCommand.php';
        require_once self::$fixturesPath.'/FooLockMethodCommand.php';
    }

    public function testLockIsReleased()
    {
        $command = new \FooLockCommand();

        $tester = new CommandTester($command);
        $this->assertSame(2, $tester->execute([]));
        $this->assertSame(2, $tester->execute([]));
    }

    public function testLockReturnsFalseIfAlreadyLockedByAnotherCommand()
    {
        $command = new \FooLockCommand();

        if (SemaphoreStore::isSupported()) {
            $store = new SemaphoreStore();
        } else {
            $store = new FlockStore();
        }

        $lock = (new LockFactory($store))->createLock($command->getName());
        $lock->acquire();

        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([]));

        $lock->release();
        $this->assertSame(2, $tester->execute([]));
    }

    public function testTheLockExpiresAfterFiveMinutesByDefault()
    {
        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->with('foo:lock3', 300.0)->willReturn($lock);

        $this->assertSame(2, new CommandTester(new \FooLock3Command($lockFactory))->execute([]));
    }

    #[TestWith([null])]
    #[TestWith([3600.0])]
    public function testTheLockIsCreatedWithTheGivenTtl(?float $ttl)
    {
        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())->method('createLock')->with('foo:lock5', $ttl)->willReturn($lock);

        $this->assertSame(2, new CommandTester(new \FooLock5InvokableCommand($lockFactory, $ttl))->execute([]));
    }

    public function testMultipleLockCallsThrowLogicException()
    {
        $command = new \FooLock2Command();

        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([]));
    }

    public function testFailingToAcquireALockDoesNotKeepTheCommandFromLockingAgain()
    {
        $failingLock = $this->createStub(SharedLockInterface::class);
        $failingLock->method('acquire')->willThrowException(new LockAcquiringException('Failed to acquire the "foo:lock3" lock.'));

        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects(static::exactly(2))->method('createLock')->willReturnOnConsecutiveCalls($failingLock, $lock);

        $tester = new CommandTester(new \FooLock3Command($lockFactory));

        try {
            $tester->execute([]);
            $this->fail('The first lock() call should have thrown.');
        } catch (LockAcquiringException) {
        }

        $this->assertSame(2, $tester->execute([]));
    }

    public function testCustomLockFactoryIsUsed()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $command = new \FooLock3Command($lockFactory);

        $tester = new CommandTester($command);

        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory->expects(static::once())->method('createLock')->willReturn($lock);
        $this->assertSame(1, $tester->execute([]));
    }

    public function testLockFactoryIsInjectedBySetter()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $command = new \FooLockCommand();
        $command->setLockFactory($lockFactory);

        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([]));
    }

    public function testInjectedLockFactoryDoesNotOverrideTheCommandOne()
    {
        $commandFactory = $this->createMock(LockFactory::class);
        $injectedFactory = $this->createMock(LockFactory::class);
        $injectedFactory->expects($this->never())->method('createLock');

        $command = new \FooLock3Command($commandFactory);
        $command->setLockFactory($injectedFactory);

        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);
        $commandFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([]));
    }

    public function testNoLockFactoryToInjectKeepsTheDefaultOne()
    {
        $command = new \FooLockCommand();
        $command->setLockFactory();

        $tester = new CommandTester($command);
        $this->assertSame(2, $tester->execute([]));
    }

    public function testNothingIsInjectedWithoutLockServices()
    {
        $container = $this->createContainer(null);

        $this->assertNull($container->get('command')->getLockFactory());
        $this->assertSame(2, new CommandTester($container->get('command'))->execute([]));
    }

    public function testTheDefaultLockResourceIsNotInjected()
    {
        $container = $this->createContainer(['default' => 'flock'], 'lock.default.factory');

        $this->assertInstanceOf(LockFactory::class, $container->get('lock_factory'));
        $this->assertNull($container->get('command')->getLockFactory());
    }

    public function testTheConsoleLockResourceIsInjected()
    {
        $container = $this->createContainer(['console' => 'flock'], 'lock.console.factory');

        $this->assertSame($container->get('lock_factory'), $container->get('command')->getLockFactory());
    }

    public function testTheConsoleLockResourceRegistersTheTargetAlias()
    {
        $container = $this->createContainer(['console' => 'flock'], null, false);

        $this->assertSame(LockFactory::class.' $consoleLockFactory', (string) $container->getAlias('.'.LockFactory::class.' $console'));
        $this->assertSame('lock.console.factory', (string) $container->getAlias(LockFactory::class.' $consoleLockFactory'));
        $this->assertFalse($container->hasAlias(LockFactory::class));
    }

    private function createContainer(?array $resources, ?string $factoryId = null, bool $compileFully = true): ContainerBuilder
    {
        if (null !== $resources && !class_exists(LockBundle::class)) {
            $this->markTestSkipped('The installed version of symfony/lock has no LockBundle.');
        }

        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.debug' => false, 'kernel.project_dir' => __DIR__]));

        if (null !== $resources) {
            $container->registerExtension(new LockBundle()->getContainerExtension());
            $container->loadFromExtension('lock', $resources);
        }

        if (null !== $factoryId) {
            $container->setAlias('lock_factory', new Alias($factoryId, true));
        }

        $container->register('command', \FooLockCommand::class)
            ->setAutowired(true)
            ->setPublic(true);

        if (!$compileFully) {
            $container->getCompilerPassConfig()->setOptimizationPasses([]);
            $container->getCompilerPassConfig()->setRemovingPasses([]);
            $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        }

        $container->compile();

        return $container;
    }

    public function testLockInvokableCommandReturnsFalseIfAlreadyLockedByAnotherCommand()
    {
        $command = new Command('foo:lock4');
        $command->setCode(new \FooLock4InvokableCommand());

        if (SemaphoreStore::isSupported()) {
            $store = new SemaphoreStore();
        } else {
            $store = new FlockStore();
        }

        $lock = (new LockFactory($store))->createLock($command->getName());
        $lock->acquire();

        $tester = new CommandTester($command);
        $this->assertSame(Command::FAILURE, $tester->execute([]));

        $lock->release();
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testInvokableCommandIsLockedOnItsNameAloneNotOnItsAliases()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $command = new Command('foo:lock-aliased');
        $command->setCode(new \FooLockAliasedInvokableCommand($lockFactory));

        $tester = new CommandTester($command);

        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $lockFactory->expects(static::once())->method('createLock')->with('foo:lock-aliased')->willReturn($lock);
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testInvokableCommandIsLockedOnTheNameItRunsUnder()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $command = new Command('foo:lock-renamed', new \FooLockAliasedInvokableCommand($lockFactory));

        $tester = new CommandTester($command);

        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $lockFactory->expects(static::once())->method('createLock')->with('foo:lock-renamed')->willReturn($lock);
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testInvokableCalledDirectlyIsLockedOnTheNameOfItsAttribute()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $invokable = new \FooLockAliasedInvokableCommand($lockFactory);

        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $lockFactory->expects(static::once())->method('createLock')->with('foo:lock-aliased')->willReturn($lock);
        $this->assertSame(Command::SUCCESS, $invokable());
    }

    public function testInvokableIsNoLongerLockedOnTheNameOfACommandThatFailed()
    {
        $names = [];
        $lockFactory = $this->createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturnCallback(function (string $name) use (&$names) {
            $names[] = $name;

            if (1 === \count($names)) {
                throw new \RuntimeException('Failed to create the lock.');
            }

            $lock = $this->createStub(SharedLockInterface::class);
            $lock->method('acquire')->willReturn(true);

            return $lock;
        });
        $invokable = new \FooLockAliasedInvokableCommand($lockFactory);

        try {
            (new CommandTester(new Command('foo:lock-renamed', $invokable)))->execute([]);
            $this->fail('The command should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Failed to create the lock.', $e->getMessage());
        }

        $this->assertSame(Command::SUCCESS, $invokable());
        $this->assertSame(['foo:lock-renamed', 'foo:lock-aliased'], $names);
    }

    public function testFailingToReleaseALockDoesNotKeepTheCommandFromLockingAgain()
    {
        $lock = $this->createMock(LockInterface::class);
        $lock->expects($this->once())->method('release')->willThrowException(new LockReleasingException('Failed to release the "foo" lock.'));

        $tester = new CommandTester(new \FooLockReleaseFailureCommand($lock));
        $this->assertSame(2, $tester->execute([]));
    }

    public function testMethodCommandsAreLockedOnTheirOwnName()
    {
        $names = [];
        $lockFactory = $this->createStub(LockFactory::class);
        $lockFactory->method('createLock')->willReturnCallback(function (string $name) use (&$names) {
            $names[] = $name;

            $lock = $this->createStub(SharedLockInterface::class);
            $lock->method('acquire')->willReturn(true);

            return $lock;
        });

        $commands = new \FooLockMethodsCommand($lockFactory);
        $this->assertSame(Command::SUCCESS, (new CommandTester(new Command(null, $commands->import(...))))->execute([]));
        $this->assertSame(Command::SUCCESS, (new CommandTester(new Command(null, $commands->export(...))))->execute([]));

        $this->assertSame(['foo:lock-methods:import', 'foo:lock-methods:export'], $names);
    }

    public function testMethodCommandWithoutClassAttributeIsLockedOnItsName()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $command = new Command(null, (new \FooLockMethodCommand($lockFactory))->import(...));

        $tester = new CommandTester($command);

        $lock = $this->createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);

        $lockFactory->expects(static::once())->method('createLock')->with('foo:lock-method')->willReturn($lock);
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }
}
