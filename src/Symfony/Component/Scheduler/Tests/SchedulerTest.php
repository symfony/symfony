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
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Scheduler;

class SchedulerTest extends TestCase
{
    public function testCanRunAndStop()
    {
        $handler = new Handler();
        $handler->clock = $clock = new MockClock();
        $schedule = (new Schedule())->add(RecurringMessage::every('1 second', new Message()));
        $scheduler = new Scheduler([Message::class => $handler], [$schedule], $clock);
        $handler->scheduler = $scheduler;

        $scheduler->run(['sleep' => 1]);

        $this->assertSame(3, $handler->count);
    }
}

class Message
{
}

class Handler
{
    public int $count = 0;
    public Scheduler $scheduler;
    public ClockInterface $clock;

    public function __invoke(Message $message): void
    {
        if (3 === ++$this->count) {
            $this->scheduler->stop();

            return;
        }

        $this->clock->sleep(1);
    }
}
