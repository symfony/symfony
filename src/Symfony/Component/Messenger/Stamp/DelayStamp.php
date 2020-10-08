<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * Apply this stamp to delay delivery of your message on a transport.
 */
final class DelayStamp implements StampInterface
{
    public const PERIOD_SECONDS = 'seconds';
    public const PERIOD_MINUTES = 'minutes';
    public const PERIOD_HOURS = 'hours';
    public const PERIOD_DAYS = 'days';
    public const PERIOD_WEEKS = 'weeks';
    public const PERIOD_MONTHS = 'months';
    public const PERIOD_YEARS = 'years';

    private $delay;

    /**
     * @param int $delay The delay in milliseconds
     */
    public function __construct(int $delay)
    {
        $this->delay = $delay;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public static function delayForSeconds(int $seconds): self
    {
        return self::delayFor($seconds, self::PERIOD_SECONDS);
    }

    public static function delayForMinutes(int $minutes): self
    {
        return self::delayFor($minutes, self::PERIOD_MINUTES);
    }

    public static function delayForHours(int $hours): self
    {
        return self::delayFor($hours, self::PERIOD_HOURS);
    }

    public static function delayUntil(\DateTimeInterface $executeAfter): self
    {
        $now = (new \DateTimeImmutable())->setTimezone($executeAfter->getTimezone());

        if ($now >= $executeAfter) {
            throw new InvalidArgumentException(sprintf('You cannot pass a date that is equal to now or is in the past. Now is "%s" and the passed date is "%s".', $now->format('Y-m-d, H:i:s'), $executeAfter->format('Y-m-d, H:i:s')));
        }

        $diff = ($executeAfter->format('U') - $now->format('U')) * 1000;

        return new self($diff);
    }

    /**
     * @param string $period A string representing a unit symbol valid for relative formats of DateTime objects
     *
     * @see https://www.php.net/manual/en/datetime.formats.relative.php#datetime.formats.relative
     */
    public static function delayFor(int $units, string $period)
    {
        if (0 >= $units) {
            throw new InvalidArgumentException(sprintf('The value of units has to be positive. You passed "%s".', $units));
        }

        $allowedPeriods = [self::PERIOD_SECONDS, self::PERIOD_MINUTES, self::PERIOD_HOURS, self::PERIOD_DAYS, self::PERIOD_WEEKS, self::PERIOD_MONTHS, self::PERIOD_YEARS];
        if (false === \in_array($period, $allowedPeriods)) {
            throw new InvalidArgumentException(sprintf('The passed period "%s" is not allowed. Allowed periods are: "%s".', $period, implode(', ', $allowedPeriods)));
        }

        $rescheduleIn = sprintf('+%s %s', $units, $period);
        $executeAfter = (new \DateTime())->modify($rescheduleIn);

        return self::delayUntil($executeAfter);
    }
}
