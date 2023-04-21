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
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

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
        string|CheckpointInterface $checkpoint,
        private readonly ClockInterface $clock = new Clock(),
    ) {
        $this->waitUntil = new \DateTimeImmutable('@0');
        if (\is_string($checkpoint)) {
            $checkpoint = new Checkpoint('scheduler_checkpoint_'.$checkpoint, $this->schedule->getLock(), $this->schedule->getState());
        }
        $this->checkpoint = $checkpoint;
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
            /** @var TriggerInterface $trigger */
            /** @var int $index */
            /** @var \DateTimeImmutable $time */
            /** @var object $message */
            [$time, $index, $trigger, $message] = $heap->extract();
            $yield = true;

            if ($time < $lastTime) {
                $time = $lastTime;
                $yield = false;
            } elseif ($time == $lastTime && $index <= $lastIndex) {
                $yield = false;
            }

            if ($nextTime = $trigger->getNextRunDate($time)) {
                $heap->insert([$nextTime, $index, $trigger, $message]);
            }

            if ($yield) {
                yield (new MessageContext($trigger, $time, $nextTime)) => $message;
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

            $heap->insert([$nextTime, $index, $recurringMessage->getTrigger(), $recurringMessage->getMessage()]);
        }

        return $this->triggerHeap = $heap;
    }
}
