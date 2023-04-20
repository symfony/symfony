<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler;

use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Scheduler\Generator\ChainMessageGenerator;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;

/**
 * @experimental
 */
final class Scheduler
{
    /**
     * @var array<MessageGenerator>
     */
    private array $generators = [];
    private int $index = 0;
    private MessageBusInterface $bus;
    private Worker $worker;

    /**
     * @param array<class-string,callable> $handlers
     * @param iterable<Schedule> $schedules
     */
    public function __construct(
        array $handlers,
        array $schedules,
        private readonly ClockInterface $clock = new Clock(),
    ) {
        foreach ($schedules as $schedule) {
            $this->addSchedule($schedule);
        }

        $this->bus = new class($handlers) implements MessageBusInterface {
            /**
             * @param array<class-string,callable> $handlers
             */
            public function __construct(private array $handlers)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = Envelope::wrap($message, $stamps);

                $this->handlers[$envelope->getMessage()::class]($envelope->getMessage());

                return $envelope;
            }
        };
    }

    public function addSchedule(Schedule $schedule): void
    {
        $this->addMessageGenerator(new MessageGenerator($schedule, 'schedule_'.$this->index++, $this->clock));
    }

    public function addMessageGenerator(MessageGenerator $generator): void
    {
        $this->generators[] = $generator;
    }

    /**
     * Schedules messages.
     *
     * Valid options are:
     *  * sleep (default: 1000000): Time in microseconds to sleep after no messages are found
     */
    public function run(array $options = []): void
    {
        $this->worker = new Worker(
            [new SchedulerTransport(new ChainMessageGenerator($this->generators))],
            $this->bus,
            clock: $this->clock,
        );

        $this->worker->run($options + ['sleep' => 1e6]);
    }

    public function stop(): void
    {
        if (isset($this->worker)) {
            $this->worker->stop();
        }
    }
}
