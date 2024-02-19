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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class AsLockedCommandTest extends TestCase
{
    protected static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = __DIR__.'/../Fixtures/';
        require_once self::$fixturesPath.'/AsLockedTestCommand.php';
        require_once self::$fixturesPath.'/AsLockedThrowsLogicExceptionTestCommand.php';
        require_once self::$fixturesPath.'/AsLockedWithCallableTestCommand.php';
    }

    public function testLockIsReleased()
    {
        $lockFactory = new LockFactory(new FlockStore());
        $command = new \AsLockedTestCommand();
        $command->setLockFactory($lockFactory);

        $tester = new CommandTester($command);
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testCommandFailsIfAlreadyLockedByAnotherCommand()
    {
        $lockFactory = new LockFactory(new FlockStore());
        $command = new \AsLockedTestCommand();
        $command->setLockFactory($lockFactory);

        $lock = $lockFactory->createLock($command->getName());
        $lock->acquire();

        $tester = new CommandTester($command);
        $this->assertSame(Command::FAILURE, $tester->execute([]));

        $lock->release();
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testMultipleLockCallsThrowLogicException()
    {
        $command = new \AsLockedThrowsLogicExceptionTestCommand();
        $lockFactory = new LockFactory(new FlockStore());
        $command->setLockFactory($lockFactory);
        $lockFactory->createLock($command->getName());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A lock is already in place.');

        $tester = new CommandTester($command);
        $this->assertSame(Command::FAILURE, $tester->execute([]));
    }

    public function testLockFromCallable()
    {
        $lockFactory = new LockFactory(new FlockStore());
        $command = new \AsLockedWithCallableTestCommand();
        $command->setLockFactory($lockFactory);

        $lock = $lockFactory->createLock('lock-test1');
        $lock->acquire();

        $tester = new CommandTester($command);
        $this->assertSame(Command::FAILURE, $tester->execute(['key' => 'test1']));
        $this->assertSame(Command::SUCCESS, $tester->execute(['key' => 'test2']));

        $lock->release();
    }
}
