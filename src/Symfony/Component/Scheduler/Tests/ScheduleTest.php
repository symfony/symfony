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
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\CallbackMessageProvider;

class ScheduleTest extends TestCase
{
    public function testCannotAddDuplicateMessage()
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));

        $this->expectException(LogicException::class);

        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));
    }

    public function testAddWithMessageProvider()
    {
        $schedule = new Schedule();
        $schedule->add(new CallbackMessageProvider(function () {
            // no-op
        }));

        $this->assertCount(1, $schedule->getRecurringMessages());
    }
}
