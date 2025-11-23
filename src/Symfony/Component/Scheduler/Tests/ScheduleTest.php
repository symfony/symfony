<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;

class ScheduleTest extends TestCase
{
    public function testCannotAddDuplicateMessage()
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));

        $this->expectException(LogicException::class);

        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));
    }

    public function testThatCannotSetLockAndLockFactoryAtTheSameTime()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot set a LockFactory with "Symfony\Component\Scheduler\Schedule::lockFactory" if a Lock is already set.');

        $lockFactory = new LockFactory(new InMemoryStore());
        $lock = $lockFactory->createLock('lock_name');

        $schedule = new Schedule();
        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()))
            ->lock($lock)
            ->lockFactory($lockFactory);
    }

    public function testThatCannotSetLockAndLockFactoryAtTheSameTime2()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You cannot set a Lock with "Symfony\Component\Scheduler\Schedule::lock" if a LockFactory is already set.');

        $lockFactory = new LockFactory(new InMemoryStore());
        $lock = $lockFactory->createLock('lock_name');

        $schedule = new Schedule();
        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()))
            ->lockFactory($lockFactory)
            ->lock($lock);
    }
}
