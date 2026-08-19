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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
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

    public function testCloneKeepsTheLockAndTheStateAndDetachesTheMessages()
    {
        $schedule = (new Schedule())
            ->lock($lock = $this->createStub(LockInterface::class))
            ->stateful($state = $this->createStub(CacheInterface::class));
        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));

        $new = clone $schedule;
        $new->add(RecurringMessage::cron('*/5 * * * *', new \stdClass()));

        $this->assertSame($lock, $new->getLock());
        $this->assertSame($state, $new->getState());
        $this->assertCount(1, $schedule->getRecurringMessages());
        $this->assertCount(2, $new->getRecurringMessages());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testWithIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/scheduler 8.2: The "Symfony\Component\Scheduler\Schedule::with()" method is deprecated and will be removed in 9.0, clone the schedule or use "add()" on a new "Symfony\Component\Scheduler\Schedule" instead.');

        (new Schedule())->with(RecurringMessage::cron('* * * * *', new \stdClass()));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testWithDoesNotCarryTheLockAndTheState()
    {
        $schedule = (new Schedule())
            ->lock($this->createStub(LockInterface::class))
            ->stateful($this->createStub(CacheInterface::class));

        $new = $schedule->with(RecurringMessage::cron('* * * * *', new \stdClass()));

        $this->assertNull($new->getLock());
        $this->assertNull($new->getState());
    }
}
