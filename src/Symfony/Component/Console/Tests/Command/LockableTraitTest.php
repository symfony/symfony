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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
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

    public function testMultipleLockCallsThrowLogicException()
    {
        $command = new \FooLock2Command();

        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([]));
    }

    public function testCustomLockFactoryIsUsed()
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $command = new \FooLock3Command($lockFactory);

        $tester = new CommandTester($command);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory->expects(static::once())->method('createLock')->willReturn($lock);
        $this->assertSame(1, $tester->execute([]));
    }
}
