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

use Cron\CronExpression;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;

class ScheduleTest extends TestCase
{
    public function testCannotAddDuplicateMessage()
    {
        if (!class_exists(CronExpression::class)) {
            $this->markTestSkipped('The "dragonmantank/cron-expression" package is required to run this test.');
        }

        $schedule = new Schedule();
        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));

        $this->expectException(LogicException::class);

        $schedule->add(RecurringMessage::cron('* * * * *', new \stdClass()));
    }
}
