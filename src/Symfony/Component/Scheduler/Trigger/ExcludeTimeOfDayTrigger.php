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
 * Excludes the same time window on every day, for example a nightly maintenance window.
 *
 * The window is a wall clock one: it keeps its local times on the days a daylight saving transition makes an hour skipped or repeated.
 * It includes both $from and $until, and wraps over midnight when $until is earlier than $from.
 */
final class ExcludeTimeOfDayTrigger extends AbstractDecoratedTrigger
{
    /**
     * How many times to skip the window before giving up and reporting no next run date, so that a trigger running only inside the window cannot loop forever.
     *
     * The window is skipped once per day, and once more on the days the clocks change.
     */
    private const MAX_ATTEMPTS = 366;

    private readonly string $from;
    private readonly string $until;

    /**
     * @param string             $from     The time the window starts at, as "H:i" or "H:i:s"
     * @param string             $until    The time the window ends at, as "H:i" or "H:i:s"
     * @param \DateTimeZone|null $timezone The timezone the window is expressed in; the one of the next run date when null
     */
    public function __construct(
        private readonly TriggerInterface $inner,
        string $from,
        string $until,
        private readonly ?\DateTimeZone $timezone = null,
    ) {
        parent::__construct($inner);

        $this->from = self::parseTimeOfDay($from, 'from');
        $this->until = self::parseTimeOfDay($until, 'until');

        if ($this->from === $this->until) {
            throw new InvalidArgumentException(\sprintf('The "$from" and "$until" arguments of "%s" must not be the same time of day, got "%s".', self::class, $this->from));
        }
    }

    public function __toString(): string
    {
        return \sprintf('%s, excluding every day from %s until %s%s', $this->inner, $this->from, $this->until, $this->timezone ? ' in '.$this->timezone->getName() : '');
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        $nextRun = $this->inner->getNextRunDate($run);

        for ($attempt = 0; null !== $nextRun && $attempt < self::MAX_ATTEMPTS; ++$attempt) {
            $localRun = $this->timezone ? $nextRun->setTimezone($this->timezone) : $nextRun;
            $timeOfDay = $localRun->format('H:i:s.u');
            $from = $this->from.'.000000';
            $until = $this->until.'.000000';

            // comparing the times of day rather than the dates they fall on keeps the window where it is configured when a daylight saving transition skips or repeats an hour
            $excluded = $from < $until
                ? $timeOfDay >= $from && $timeOfDay <= $until
                : $timeOfDay >= $from || $timeOfDay <= $until;

            if (!$excluded) {
                return $nextRun;
            }

            // the window ends when the local time reaches $until, or just before the clocks change if they change first
            $secondsToEnd = (self::secondsOfDay($this->until) - self::secondsOfDay($localRun->format('H:i:s')) + 86400) % 86400;
            $end = \DateTimeImmutable::createFromTimestamp($localRun->getTimestamp() + $secondsToEnd);
            $transitions = $localRun->getTimezone()->getTransitions($localRun->getTimestamp(), $end->getTimestamp() + 1);

            if (isset($transitions[1])) {
                $end = \DateTimeImmutable::createFromTimestamp($transitions[1]['ts'])->modify('-1 microsecond');
            }

            $nextRun = $this->inner->getNextRunDate($end->setTimezone($nextRun->getTimezone()));
        }

        return null;
    }

    private static function parseTimeOfDay(string $time, string $argument): string
    {
        if (!preg_match('/\A(\d{1,2}):(\d{2})(?::(\d{2}))?\z/', $time, $matches)
            || 23 < $matches[1]
            || 59 < $matches[2]
            || 59 < ($matches[3] ?? 0)
        ) {
            throw new InvalidArgumentException(\sprintf('The "$%s" argument of "%s" must be a time of day as "H:i" or "H:i:s", got "%s".', $argument, self::class, $time));
        }

        return \sprintf('%02d:%02d:%02d', $matches[1], $matches[2], $matches[3] ?? 0);
    }

    private static function secondsOfDay(string $time): int
    {
        [$hours, $minutes, $seconds] = sscanf($time, '%d:%d:%d');

        return $hours * 3600 + $minutes * 60 + $seconds;
    }
}
