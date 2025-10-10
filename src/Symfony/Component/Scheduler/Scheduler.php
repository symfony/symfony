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

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\Generator\MessageGenerator;

final class Scheduler
{
    /**
     * @var array<MessageGenerator>
     */
    private array $generators = [];
    private int $index = 0;
    private bool $shouldStop = false;

    /**
     * @param iterable<Schedule> $schedules
     */
    public function __construct(
        private readonly array $handlers,
        array $schedules,
        private readonly ClockInterface $clock = new Clock(),
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
        foreach ($schedules as $schedule) {
            $this->addSchedule($schedule);
        }
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
        $options += ['sleep' => 1e6];

        while (!$this->shouldStop) {
            $start = $this->clock->now();

            $ran = false;
            foreach ($this->generators as $generator) {
                foreach ($generator->getMessages() as $context => $message) {
                    if (!$this->dispatcher) {
                        $this->handlers[$message::class]($message);
                        $ran = true;

                        continue;
                    }

                    $preRunEvent = new PreRunEvent($generator->getSchedule(), $context, $message);
                    $this->dispatcher->dispatch($preRunEvent);

                    if ($preRunEvent->shouldCancel()) {
                        continue;
                    }

                    try {
                        $result = $this->handlers[$message::class]($message);
                        $ran = true;

                        $this->dispatcher->dispatch(new PostRunEvent($generator->getSchedule(), $context, $message, $result));
                    } catch (\Throwable $error) {
                        $failureEvent = new FailureEvent($generator->getSchedule(), $context, $message, $error);
                        $this->dispatcher->dispatch($failureEvent);

                        if (!$failureEvent->shouldIgnore()) {
                            throw $error;
                        }
                    }
                }
            }

            if (!$ran) {
                if (0 < $sleep = (int) ($options['sleep'] - 1e6 * ($this->clock->now()->format('U.u') - $start->format('U.u')))) {
                    $this->clock->sleep($sleep / 1e6);
                }
            }
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }
}
