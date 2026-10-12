<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Dominic Tubach <dominic.tubach@to.com>
 */
class ClockMock
{
    private static $now;
    private static $initialGlobalClock;

    public static function withClockMock($enable = null): ?bool
    {
        if (null === $enable) {
            return null !== self::$now;
        }

        self::$now = is_numeric($enable) ? (float) $enable : ($enable ? microtime(true) : null);
        self::$initialGlobalClock = \class_exists(Clock::class, false) ? Clock::get() : null;

        return null;
    }

    public static function time(): int
    {
        if (null === self::$now) {
            return \time();
        }

        return (int) self::now();
    }

    public static function sleep($s): int
    {
        if (null === self::$now) {
            return \sleep($s);
        }

        if ($clock = self::getMockClock()) {
            $clock->sleep((int) $s);
        } else {
            self::$now += (int) $s;
        }

        return 0;
    }

    public static function usleep($us): void
    {
        if (null === self::$now) {
            \usleep($us);
        } elseif ($clock = self::getMockClock()) {
            $clock->sleep($us / 1000000);
        } else {
            self::$now += $us / 1000000;
        }
    }

    /**
     * @return string|float
     */
    public static function microtime($asFloat = false)
    {
        if (null === self::$now) {
            return \microtime($asFloat);
        }

        $now = self::now();

        if ($asFloat) {
            return $now;
        }

        return \sprintf('%0.6f00 %d', $now - (int) $now, (int) $now);
    }

    public static function date($format, $timestamp = null): string
    {
        if (null === $timestamp) {
            $timestamp = self::time();
        }

        return \date($format, $timestamp);
    }

    public static function gmdate($format, $timestamp = null): string
    {
        if (null === $timestamp) {
            $timestamp = self::time();
        }

        return \gmdate($format, $timestamp);
    }

    /**
     * @return array|int|float
     */
    public static function hrtime($asNumber = false)
    {
        if (null === self::$now) {
            return \hrtime($asNumber);
        }

        $now = self::now();
        $ns = ($now - (int) $now) * 1000000000;

        if ($asNumber) {
            $number = \sprintf('%d%09d', (int) $now, $ns);

            return \PHP_INT_SIZE === 8 ? (int) $number : (float) $number;
        }

        return [(int) $now, (int) $ns];
    }

    /**
     * @return false|int
     */
    public static function strtotime(string $datetime, ?int $timestamp = null)
    {
        if (null === $timestamp) {
            $timestamp = self::time();
        }

        return \strtotime($datetime, $timestamp);
    }

    public static function register($class): void
    {
        $self = static::class;

        $mockedNs = [substr($class, 0, strrpos($class, '\\'))];
        if (0 < strpos($class, '\\Tests\\')) {
            $ns = str_replace('\\Tests\\', '\\', $class);
            $mockedNs[] = substr($ns, 0, strrpos($ns, '\\'));
        } elseif (str_starts_with($class, 'Tests\\')) {
            $mockedNs[] = substr($class, 6, strrpos($class, '\\') - 6);
        }
        foreach ($mockedNs as $ns) {
            if (\function_exists($ns.'\time')) {
                continue;
            }
            eval(<<<EOPHP
namespace $ns;

function time()
{
    return \\$self::time();
}

function microtime(\$asFloat = false)
{
    return \\$self::microtime(\$asFloat);
}

function sleep(\$s)
{
    return \\$self::sleep(\$s);
}

function usleep(\$us)
{
    \\$self::usleep(\$us);
}

function date(\$format, \$timestamp = null)
{
    return \\$self::date(\$format, \$timestamp);
}

function gmdate(\$format, \$timestamp = null)
{
    return \\$self::gmdate(\$format, \$timestamp);
}

function hrtime(\$asNumber = false)
{
    return \\$self::hrtime(\$asNumber);
}

function strtotime(\$datetime, \$timestamp = null)
{
    return \\$self::strtotime(\$datetime, \$timestamp);
}
EOPHP
            );
        }
    }

    private static function now(): float
    {
        if (!$clock = self::getMockClock()) {
            return self::$now;
        }

        $now = $clock->now();

        return $now->getTimestamp() + (int) $now->format('u') / 1000000;
    }

    /**
     * Returns the global clock when it is a MockClock that was set after enabling the mock.
     */
    private static function getMockClock(): ?MockClock
    {
        if (!\class_exists(Clock::class, false) || !($clock = Clock::get()) instanceof MockClock || $clock === self::$initialGlobalClock) {
            return null;
        }

        return $clock;
    }
}
