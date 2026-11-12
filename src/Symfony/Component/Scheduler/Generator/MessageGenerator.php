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
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\StatefulTriggerInterface;

final class MessageGenerator implements MessageGeneratorInterface
{
    private ?Schedule $schedule = null;
    private TriggerHeap $triggerHeap;
    private bool $heapInitialized = false;
    /** @var array<string, \DateTimeImmutable|null> */
    private array $addedAt = [];
    private ?\DateTimeImmutable $waitUntil;

    public function __construct(
        private readonly ScheduleProviderInterface $scheduleProvider,
        private readonly string $name,
        private readonly ClockInterface $clock = new Clock(),
        private ?CheckpointInterface $checkpoint = null,
    ) {
        $this->waitUntil = \DateTimeImmutable::createFromTimestamp(0);
    }

    /**
     * @return \Generator<MessageContext, object>
     */
    public function getMessages(): \Generator
    {
        $checkpoint = $this->checkpoint();

        if ($this->schedule?->shouldRestart()) {
            unset($this->triggerHeap);
            $this->waitUntil = \DateTimeImmutable::createFromTimestamp(0);
            $this->schedule->setRestart(false);
        }

        if (!$this->waitUntil
            || $this->waitUntil > ($now = $this->clock->now())
            || !$checkpoint->acquire($now)
        ) {
            return;
        }

        $startTime = $checkpoint->from();
        // the checkpoint has the timezone of the last run, and triggers without one use the timezone of the date they get
        $lastTime = $checkpoint->time()->setTimezone($now->getTimezone());
        $lastIndex = $checkpoint->index();
        $heap = $this->heap($lastTime, $startTime, $lastIndex, $now);
        $lock = $this->getSchedule()->getLock();

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

            if ($nextTime = $trigger->getNextRunDate($time)) {
                if ($nextTime <= $time) {
                    throw new LogicException(\sprintf('The "%s" trigger does not move the run date forward. Its "getNextRunDate()" method must return a date strictly after the given one.', $trigger));
                }

                $heap->insert([$nextTime, $index, $recurringMessage]);

                if ($nextTime <= $now && $this->schedule->shouldProcessOnlyLastMissedRun()) {
                    $yield = false;
                }
            }

            if ($yield) {
                $context = new MessageContext($this->name, $id, $trigger, $time, $nextTime);
                $lockLost = false;
                try {
                    foreach ($recurringMessage->getMessages($context) as $message) {
                        yield $context => $message;
                    }
                } finally {
                    // when the lock expired during the run and another worker acquired it, that worker continues from the checkpoint
                    $lockLost = $lock?->isExpired() && !$checkpoint->acquire($now);

                    if (!$lockLost) {
                        $checkpoint->save($time, $index);
                    }
                }

                if ($lockLost) {
                    return;
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

    private function heap(\DateTimeImmutable $time, \DateTimeImmutable $startTime, int $lastIndex, \DateTimeImmutable $now): TriggerHeap
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
        $probeTime = !$this->heapInitialized && $lastIndex >= 0 ? $time->setTimezone(new \DateTimeZone('UTC'))->modify('-1 microsecond')->setTimezone($time->getTimezone()) : $time;
        $rebuilt = $this->heapInitialized;
        $this->heapInitialized = true;
        $addedAt = [];

        foreach ($this->getSchedule()->getRecurringMessages() as $index => $recurringMessage) {
            $trigger = $recurringMessage->getTrigger();
            $id = $recurringMessage->getId();

            if ($trigger instanceof StatefulTriggerInterface) {
                $trigger->continue($startTime);
            }

            // a message added to a running schedule starts from the time it was added, its earlier runs were never due
            $added = $addedAt[$id] = $rebuilt ? (\array_key_exists($id, $this->addedAt) ? $this->addedAt[$id] : $now) : null;

            if (!$nextTime = $trigger->getNextRunDate($added && $added > $probeTime ? $added : $probeTime)) {
                continue;
            }

            $heap->insert([$nextTime, $index, $recurringMessage]);
        }

        $this->addedAt = $addedAt;

        return $this->triggerHeap = $heap;
    }

    private function checkpoint(): Checkpoint
    {
        return $this->checkpoint ??= new Checkpoint('scheduler_checkpoint_'.$this->name, $this->getSchedule()->getLock(), $this->getSchedule()->getState());
    }
}
