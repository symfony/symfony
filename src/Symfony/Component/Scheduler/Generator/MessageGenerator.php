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
use Symfony\Component\Scheduler\Trigger\StatefulTriggerInterface;

final class MessageGenerator implements MessageGeneratorInterface
{
    private ?Schedule $schedule = null;
    private TriggerHeap $triggerHeap;
    private bool $heapInitialized = false;
    private ?\DateTimeImmutable $waitUntil;

    public function __construct(
        private readonly ScheduleProviderInterface $scheduleProvider,
        private readonly string $name,
        private readonly ClockInterface $clock = new Clock(),
        private ?CheckpointInterface $checkpoint = null,
    ) {
        $this->waitUntil = new \DateTimeImmutable('@0');
    }

    /**
     * @return \Generator<MessageContext, object>
     */
    public function getMessages(): \Generator
    {
        $checkpoint = $this->checkpoint();

        if ($this->schedule?->shouldRestart()) {
            unset($this->triggerHeap);
            $this->waitUntil = new \DateTimeImmutable('@0');
            $this->schedule->setRestart(false);
        }

        if (!$this->waitUntil
            || $this->waitUntil > ($now = $this->clock->now())
            || !$checkpoint->acquire($now)
        ) {
            return;
        }

        $startTime = $checkpoint->from();
        $lastTime = $checkpoint->time();
        $lastIndex = $checkpoint->index();
        $heap = $this->heap($lastTime, $startTime, $lastIndex);

        while (!$heap->isEmpty() && $heap->top()[0] <= $now) {
            /** @var \DateTimeImmutable $time */
            /** @var int $index */
            /** @var RecurringMessage $recurringMessage */
            [$time, $index, $recurringMessage] = $heap->extract();
            $id = $recurringMessage->getId();
            $trigger = $recurringMessage->getTrigger();
            $yield = true;

            if ($time < $lastTime) {
                $time = $lastTime;
                $yield = false;
            } elseif ($time == $lastTime && $index <= $lastIndex) {
                $yield = false;
            }

            $nextTime = $trigger->getNextRunDate($time);

            if ($this->schedule->shouldProcessOnlyLastMissedRun()) {
                while ($nextTime && $nextTime < $this->clock->now()) {
                    $nextTime = $trigger->getNextRunDate($nextTime);
                }
            }

            if ($nextTime) {
                $heap->insert([$nextTime, $index, $recurringMessage]);
            }

            if ($yield) {
                $context = new MessageContext($this->name, $id, $trigger, $time, $nextTime);
                try {
                    foreach ($recurringMessage->getMessages($context) as $message) {
                        yield $context => $message;
                    }
                } finally {
                    $checkpoint->save($time, $index);
                }
            }
        }

        $this->waitUntil = $heap->isEmpty() ? null : $heap->top()[0];

        $checkpoint->release($now, $this->waitUntil);
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= $this->scheduleProvider->getSchedule();
    }

    private function heap(\DateTimeImmutable $time, \DateTimeImmutable $startTime, int $lastIndex): TriggerHeap
    {
        if (isset($this->triggerHeap) && $this->triggerHeap->time <= $time) {
            return $this->triggerHeap;
        }

        $heap = new TriggerHeap($time);

        // On the very first heap build of this instance — a new process picking up a
        // checkpoint that already yielded part of the messages due at $time (lastIndex
        // >= 0) — probe one microsecond before $time so triggers, whose getNextRunDate()
        // is strictly-after, re-emit entries due at exactly $time. The skip logic in
        // getMessages() then filters out already-yielded indices, letting the
        // un-processed remainder through. Subsequent rebuilds happen after a normal
        // yield-then-advance cycle (not after a partial yield), so re-probing $time
        // would re-emit entries that are already advanced past.
        $probeTime = !$this->heapInitialized && $lastIndex >= 0 ? $time->modify('-1 microsecond') : $time;
        $this->heapInitialized = true;

        foreach ($this->getSchedule()->getRecurringMessages() as $index => $recurringMessage) {
            $trigger = $recurringMessage->getTrigger();

            if ($trigger instanceof StatefulTriggerInterface) {
                $trigger->continue($startTime);
            }

            if (!$nextTime = $trigger->getNextRunDate($probeTime)) {
                continue;
            }

            $heap->insert([$nextTime, $index, $recurringMessage]);
        }

        return $this->triggerHeap = $heap;
    }

    private function checkpoint(): Checkpoint
    {
        return $this->checkpoint ??= new Checkpoint('scheduler_checkpoint_'.$this->name, $this->getSchedule()->getLock(), $this->getSchedule()->getState());
    }
}
