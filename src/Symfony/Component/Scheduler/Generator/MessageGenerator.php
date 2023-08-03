<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Generator;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

final class MessageGenerator implements MessageGeneratorInterface
{
    private Schedule $schedule;
    private TriggerHeap $triggerHeap;
    private ?\DateTimeImmutable $waitUntil;

    public function __construct(
        private readonly ScheduleProviderInterface $scheduleProvider,
        private readonly string $name,
        private readonly ClockInterface $clock = new Clock(),
        private ?CheckpointInterface $checkpoint = null,
    ) {
        $this->waitUntil = new \DateTimeImmutable('@0');
    }

    public function getMessages(): \Generator
    {
        $checkpoint = $this->checkpoint();

        if (!$this->waitUntil
            || $this->waitUntil > ($now = $this->clock->now())
            || !$checkpoint->acquire($now)
        ) {
            return;
        }

        $lastTime = $checkpoint->time();
        $lastIndex = $checkpoint->index();
        $heap = $this->heap($lastTime);

        while (!$heap->isEmpty() && $heap->top()[0] <= $now) {
            /** @var \DateTimeImmutable $time */
            /** @var int $index */
            /** @var RecurringMessage $recurringMessage */
            [$time, $index, $recurringMessage] = $heap->extract();
            $id = $recurringMessage->getId();
            $message = $recurringMessage->getMessage();
            $trigger = $recurringMessage->getTrigger();
            $yield = true;

            if ($time < $lastTime) {
                $time = $lastTime;
                $yield = false;
            } elseif ($time == $lastTime && $index <= $lastIndex) {
                $yield = false;
            }

            if ($nextTime = $trigger->getNextRunDate($time)) {
                $heap->insert([$nextTime, $index, $recurringMessage]);
            }

            if ($yield) {
                yield (new MessageContext($this->name, $id, $trigger, $time, $nextTime)) => $message;
                $checkpoint->save($time, $index);
            }
        }

        $this->waitUntil = $heap->isEmpty() ? null : $heap->top()[0];

        $checkpoint->release($now, $this->waitUntil);
    }

    private function heap(\DateTimeImmutable $time): TriggerHeap
    {
        if (isset($this->triggerHeap) && $this->triggerHeap->time <= $time) {
            return $this->triggerHeap;
        }

        $heap = new TriggerHeap($time);

        foreach ($this->schedule()->getRecurringMessages() as $index => $recurringMessage) {
            if (!$nextTime = $recurringMessage->getTrigger()->getNextRunDate($time)) {
                continue;
            }

            $heap->insert([$nextTime, $index, $recurringMessage]);
        }

        return $this->triggerHeap = $heap;
    }

    private function schedule(): Schedule
    {
        return $this->schedule ??= $this->scheduleProvider->getSchedule();
    }

    private function checkpoint(): Checkpoint
    {
        return $this->checkpoint ??= new Checkpoint('scheduler_checkpoint_'.$this->name, $this->schedule()->getLock(), $this->schedule()->getState());
    }
}
