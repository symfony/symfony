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
final class PeriodicalTrigger implements TriggerInterface
{
    public function __construct(
        private readonly int $intervalInSeconds,
        private readonly \DateTimeImmutable $from = new \DateTimeImmutable(),
        private readonly \DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01'),
    ) {
        if (0 >= $this->intervalInSeconds) {
            throw new InvalidArgumentException('The "$intervalInSeconds" argument must be greater then zero.');
        }
    }

    public static function create(
        string|int|\DateInterval $interval,
        string|\DateTimeImmutable $from = new \DateTimeImmutable(),
        string|\DateTimeImmutable $until = new \DateTimeImmutable('3000-01-01'),
    ): self {
        if (\is_string($from)) {
            $from = new \DateTimeImmutable($from);
        }
        if (\is_string($until)) {
            $until = new \DateTimeImmutable($until);
        }
        if (\is_string($interval)) {
            if ('P' === $interval[0]) {
                $interval = new \DateInterval($interval);
            } elseif (ctype_digit($interval)) {
                self::ensureIntervalSize($interval);
                $interval = (int) $interval;
            } else {
                throw new InvalidArgumentException(sprintf('The interval "%s" for a periodical message is invalid.', $interval));
            }
        }
        if (!\is_int($interval)) {
            $interval = self::calcInterval($from, $from->add($interval));
        }

        return new self($interval, $from, $until);
    }

    public static function fromPeriod(\DatePeriod $period): self
    {
        $startDate = \DateTimeImmutable::createFromInterface($period->getStartDate());
        $nextDate = $startDate->add($period->getDateInterval());
        $from = $period->include_start_date ? $startDate : $nextDate;
        $interval = self::calcInterval($startDate, $nextDate);

        $until = $period->getEndDate()
            ? \DateTimeImmutable::createFromInterface($period->getEndDate())
            : $startDate->modify($period->getRecurrences() * $interval.' seconds');

        return new self($interval, $from, $until);
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        if ($this->from > $run) {
            return $this->from;
        }
        if ($this->until <= $run) {
            return null;
        }

        $delta = $run->format('U.u') - $this->from->format('U.u');
        $recurrencesPassed = (int) ($delta / $this->intervalInSeconds);
        $nextRunTimestamp = ($recurrencesPassed + 1) * $this->intervalInSeconds + $this->from->getTimestamp();
        /** @var \DateTimeImmutable $nextRun */
        $nextRun = \DateTimeImmutable::createFromFormat('U.u', $nextRunTimestamp.$this->from->format('.u'));

        return $this->until > $nextRun ? $nextRun : null;
    }

    private static function calcInterval(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        if (8 <= \PHP_INT_SIZE) {
            return $to->getTimestamp() - $from->getTimestamp();
        }

        // @codeCoverageIgnoreStart
        $interval = $to->format('U') - $from->format('U');
        self::ensureIntervalSize(abs($interval));

        return (int) $interval;
        // @codeCoverageIgnoreEnd
    }

    private static function ensureIntervalSize(string|float $interval): void
    {
        if ($interval > \PHP_INT_MAX) {
            throw new InvalidArgumentException('The interval for a periodical message is too big. If you need to run it once, use "$until" argument.');
        }
    }
}
