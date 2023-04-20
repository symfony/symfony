<?php

namespace Symfony\Component\Scheduler\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Scheduler;

class SchedulerTest extends TestCase
{
    /**
     * @test
     */
    public function can_run(): void
    {
        $handler = new Handler();
        $schedule = (new Schedule())->add(RecurringMessage::every('1 microseconds', new Message()));
        $scheduler = new Scheduler([Message::class => $handler], [$schedule]);
        $handler->scheduler = $scheduler;

        $scheduler->run(['sleep' => 0]);

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

    public function __invoke(Message $message): void
    {
        if (3 === ++$this->count) {
            $this->scheduler->stop();
        }
    }
}
