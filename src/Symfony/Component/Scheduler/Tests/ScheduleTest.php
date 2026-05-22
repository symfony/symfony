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
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Contracts\Cache\CacheInterface;

class ScheduleTest extends TestCase
{
    public function testCannotAddDuplicateMessage()
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));

        $this->expectException(LogicException::class);

        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));
    }

    public function testSettingsPersistedOnWithCall()
    {
        $schedule = new Schedule();
        $schedule->lock(self::createStub(LockInterface::class));
        $schedule->stateful(self::createStub(CacheInterface::class));
        $schedule->setRestart(true);
        $schedule->processOnlyLastMissedRun(true);
        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));

        self::assertCount(1, $schedule->getRecurringMessages());

        $nextSchedule = $schedule->with(RecurringMessage::every('1 minute', new \stdClass()));

        self::assertCount(1, $nextSchedule->getRecurringMessages());
        self::assertEquals($schedule->getLock(), $nextSchedule->getLock());
        self::assertEquals($schedule->getState(), $nextSchedule->getState());
        self::assertEquals($schedule->shouldRestart(), $nextSchedule->shouldRestart());
        self::assertEquals($schedule->shouldProcessOnlyLastMissedRun(), $nextSchedule->shouldProcessOnlyLastMissedRun());
    }
}
