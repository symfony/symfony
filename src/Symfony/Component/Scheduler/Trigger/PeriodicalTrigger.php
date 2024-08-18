<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;

class PeriodicalTrigger implements StatefulTriggerInterface
{
    private float $intervalInSeconds = 0.0;
    private ?\DateTimeImmutable $from;
    private \DateTimeImmutable $until;
    private \DatePeriod $period;
    private string $description;
    private string|int|float|\DateInterval $interval;

    public function __construct(
        string|int|float|\DateInterval $interval,
        string|\DateTimeImmutable|null $from = null,
        string|\DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01'),
    ) {
        $this->from = \is_string($from) ? new \DateTimeImmutable($from) : $from;
        $this->until = \is_string($until) ? new \DateTimeImmutable($until) : $until;

        if (\is_int($interval) || \is_float($interval) || \is_string($interval) && ctype_digit($interval)) {
            if (0 >= (int) $interval) {
                throw new InvalidArgumentException('The "$interval" argument must be greater than zero.');
            }

            $this->intervalInSeconds = (int) $interval;
            $this->description = \sprintf('every %d seconds', $this->intervalInSeconds);

            return;
        }

        try {
            if (\is_string($interval) && 'P' === ($interval[0] ?? '')) {
                $this->intervalInSeconds = $this->calcInterval(new \DateInterval($interval));
                $this->description = \sprintf('every %d seconds (%s)', $this->intervalInSeconds, $interval);

                return;
            }

            $i = $interval;
            if (\is_string($interval)) {
                $this->description = \sprintf('every %s', $interval);
                $i = \DateInterval::createFromDateString($interval);
            } else {
                $a = (array) $interval;
                $this->description = $a['from_string'] ? $a['date_string'] : 'DateInterval';
            }

            if ($this->canBeConvertedToSeconds($i)) {
                $this->intervalInSeconds = $this->calcInterval($i);
                if ('DateInterval' === $this->description) {
                    $this->description = \sprintf('every %s seconds', $this->intervalInSeconds);
                }
            } else {
                $this->interval = $i;
            }
        } catch (\Exception $e) {
            throw new InvalidArgumentException(\sprintf('Invalid interval "%s": ', $interval instanceof \DateInterval ? 'instance of \DateInterval' : $interval).$e->getMessage(), 0, $e);
        }
    }

    public function __toString(): string
    {
        return $this->description;
    }

    public function continue(\DateTimeImmutable $startedAt): void
    {
        $this->from ??= $startedAt;
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        $this->from ??= $run;

        if ($this->intervalInSeconds) {
            if ($this->until <= $run) {
                return null;
            }

            $fromDate = $this->from;
            $from = (float) $fromDate->format('U.u');
            $delta = $run->format('U.u') - $from;
            $recurrencesPassed = floor($delta / $this->intervalInSeconds);
            $nextRunTimestamp = \sprintf('%.6F', ($recurrencesPassed + 1) * $this->intervalInSeconds + $from);
            $nextRun = \DateTimeImmutable::createFromFormat('U.u', $nextRunTimestamp)->setTimezone($fromDate->getTimezone());

            if ($this->from > $nextRun) {
                return $this->from;
            }

            return $this->until > $nextRun ? $nextRun : null;
        }

        $this->period ??= new \DatePeriod($this->from, $this->interval, $this->until);
        $iterator = $this->period->getIterator();
        while ($run >= $next = $iterator->current()) {
            $iterator->next();
            if (!$iterator->valid()) {
                return null;
            }
        }

        return $next;
    }

    private function canBeConvertedToSeconds(\DateInterval $interval): bool
    {
        $a = (array) $interval;
        if ($a['from_string']) {
            return preg_match('#^\s*\d+\s*(sec|second|min|minute|hour)s?\s*$#', $a['date_string']);
        }

        return !$interval->y && !$interval->m && !$interval->d;
    }

    private function calcInterval(\DateInterval $interval): float
    {
        return (float) (new \DateTimeImmutable('@0'))->add($interval)->format('U.u');
    }
}
