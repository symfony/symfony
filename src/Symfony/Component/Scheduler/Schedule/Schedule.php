<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Schedule;

use Psr\Clock\ClockInterface;
use Symfony\Component\Scheduler\State\StateInterface;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

final class Schedule implements ScheduleInterface
{
    /**
     * @var array<int, array{TriggerInterface, object}>
     */
    private readonly array $schedule;
    private ScheduleHeap $scheduleHeap;
    private ?\DateTimeImmutable $waitUntil;

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly StateInterface $state,
        ScheduleConfig $scheduleConfig,
    ) {
        $this->schedule = $scheduleConfig->getSchedule();
        $this->waitUntil = new \DateTimeImmutable('@0');
    }

    public function getMessages(): \Generator
    {
        if (!$this->waitUntil ||
            $this->waitUntil > ($now = $this->clock->now()) ||
            !$this->state->acquire($now)
        ) {
            return;
        }

        $lastTime = $this->state->time();
        $lastIndex = $this->state->index();
        $heap = $this->heap($lastTime);

        while (!$heap->isEmpty() && $heap->top()[0] <= $now) {
            /** @var TriggerInterface $trigger */
            [$time, $index, $trigger, $message] = $heap->extract();
            $yield = true;

            if ($time < $lastTime) {
                $time = $lastTime;
                $yield = false;
            } elseif ($time == $lastTime && $index <= $lastIndex) {
                $yield = false;
            }

            if ($nextTime = $trigger->nextTo($time)) {
                $heap->insert([$nextTime, $index, $trigger, $message]);
            }

            if ($yield) {
                yield $message;
                $this->state->save($time, $index);
            }
        }

        $this->waitUntil = $heap->isEmpty() ? null : $heap->top()[0];

        $this->state->release($now, $this->waitUntil);
    }

    private function heap(\DateTimeImmutable $time): ScheduleHeap
    {
        if (isset($this->scheduleHeap) && $this->scheduleHeap->time <= $time) {
            return $this->scheduleHeap;
        }

        $heap = new ScheduleHeap($time);

        foreach ($this->schedule as $index => [$trigger, $message]) {
            /** @var TriggerInterface $trigger */
            if (!$nextTime = $trigger->nextTo($time)) {
                continue;
            }

            $heap->insert([$nextTime, $index, $trigger, $message]);
        }

        return $this->scheduleHeap = $heap;
    }
}
