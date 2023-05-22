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

/**
 * @experimental
 */
class PeriodicalTrigger implements TriggerInterface, \Stringable
{
    private float $intervalInSeconds = 0.0;
    private \DateTimeImmutable $from;
    private \DateTimeImmutable $until;
    private \DatePeriod $period;
    private string $description;

    public function __construct(
        string|int|float|\DateInterval $interval,
        string|\DateTimeImmutable $from = new \DateTimeImmutable(),
        string|\DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01'),
    ) {
        $this->from = \is_string($from) ? new \DateTimeImmutable($from) : $from;
        $this->until = \is_string($until) ? new \DateTimeImmutable($until) : $until;

        if (\is_int($interval) || \is_float($interval)) {
            if (0 >= $interval) {
                throw new InvalidArgumentException('The "$interval" argument must be greater than zero.');
            }

            $this->intervalInSeconds = $interval;
            $this->description = sprintf('every %d seconds', $this->intervalInSeconds);

            return;
        }

        if (\is_string($interval) && ctype_digit($interval)) {
            $this->intervalInSeconds = (int) $interval;
            $this->description = sprintf('every %d seconds', $this->intervalInSeconds);

            return;
        }

        try {
            if (\is_string($interval) && 'P' === ($interval[0] ?? '')) {
                $this->intervalInSeconds = $this->calcInterval(new \DateInterval($interval));
                $this->description = sprintf('every %d seconds (%s)', $this->intervalInSeconds, $interval);

                return;
            }

            $i = $interval;
            if (\is_string($interval)) {
                $this->description = sprintf('every %s', $interval);
                $i = \DateInterval::createFromDateString($interval);
            } else {
                $a = (array) $interval;
                $this->description = \PHP_VERSION_ID >= 80200 && $a['from_string'] ? $a['date_string'] : 'DateInterval';
            }

            if ($this->canBeConvertedToSeconds($i)) {
                $this->intervalInSeconds = $this->calcInterval($i);
                if ('DateInterval' === $this->description) {
                    $this->description = sprintf('every %s seconds', $this->intervalInSeconds);
                }
            } else {
                $this->period = new \DatePeriod($this->from, $i, $this->until);
            }
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Invalid interval "%s": ', $interval instanceof \DateInterval ? 'instance of \DateInterval' : $interval).$e->getMessage(), 0, $e);
        }
    }

    public function __toString(): string
    {
        return $this->description;
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        if ($this->intervalInSeconds) {
            if ($this->from > $run) {
                return $this->from;
            }
            if ($this->until <= $run) {
                return null;
            }

            $from = $this->from->format('U.u');
            $delta = $run->format('U.u') - $from;
            $recurrencesPassed = floor($delta / $this->intervalInSeconds);
            $nextRunTimestamp = sprintf('%.6F', ($recurrencesPassed + 1) * $this->intervalInSeconds + $from);
            $nextRun = \DateTimeImmutable::createFromFormat('U.u', $nextRunTimestamp, $this->from->getTimezone());

            return $this->until > $nextRun ? $nextRun : null;
        }

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
        if (\PHP_VERSION_ID >= 80200) {
            if ($a['from_string']) {
                return preg_match('#^\s*\d+\s*(sec|second|min|minute|hour)s?\s*$#', $a['date_string']);
            }
        } elseif ($a['weekday'] || $a['weekday_behavior'] || $a['first_last_day_of'] || $a['days'] || $a['special_type'] || $a['special_amount'] || $a['have_weekday_relative'] || $a['have_special_relative']) {
            return false;
        }

        return !$interval->y && !$interval->m && !$interval->d;
    }

    private function calcInterval(\DateInterval $interval): float
    {
        return $this->from->setTimestamp(0)->add($interval)->format('U.u');
    }
}
