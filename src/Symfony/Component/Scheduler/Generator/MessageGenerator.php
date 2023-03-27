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

/**
 * @experimental
 */
final class MessageGenerator implements MessageGeneratorInterface
{
    private TriggerHeap $triggerHeap;
    private ?\DateTimeImmutable $waitUntil;
    private CheckpointInterface $checkpoint;

    public function __construct(
        private readonly Schedule $schedule,
        private readonly string $name,
        private readonly ClockInterface $clock = new Clock(),
        CheckpointInterface $checkpoint = null,
    ) {
        $this->waitUntil = new \DateTimeImmutable('@0');
        $this->checkpoint = $checkpoint ?? new Checkpoint('scheduler_checkpoint_'.$this->name, $this->schedule->getLock(), $this->schedule->getState());
    }

    public function getMessages(): \Generator
    {
        if (!$this->waitUntil
            || $this->waitUntil > ($now = $this->clock->now())
            || !$this->checkpoint->acquire($now)
        ) {
            return;
        }

        $lastTime = $this->checkpoint->time();
        $lastIndex = $this->checkpoint->index();
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
                $this->checkpoint->save($time, $index);
            }
        }

        $this->waitUntil = $heap->isEmpty() ? null : $heap->top()[0];

        $this->checkpoint->release($now, $this->waitUntil);
    }

    private function heap(\DateTimeImmutable $time): TriggerHeap
    {
        if (isset($this->triggerHeap) && $this->triggerHeap->time <= $time) {
            return $this->triggerHeap;
        }

        $heap = new TriggerHeap($time);

        foreach ($this->schedule->getRecurringMessages() as $index => $recurringMessage) {
            if (!$nextTime = $recurringMessage->getTrigger()->getNextRunDate($time)) {
                continue;
            }

            $heap->insert([$nextTime, $index, $recurringMessage]);
        }

        return $this->triggerHeap = $heap;
    }
}
