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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Dominic Tubach <dominic.tubach@to.com>
 */
class ClockMock
{
    private static $now;

    public static function withClockMock($enable = null): ?bool
    {
        if (null === $enable) {
            return null !== self::$now;
        }

        self::$now = is_numeric($enable) ? (float) $enable : ($enable ? microtime(true) : null);

        return null;
    }

    public static function time(): int
    {
        if (null === self::$now) {
            return \time();
        }

        return (int) self::$now;
    }

    public static function sleep($s): int
    {
        if (null === self::$now) {
            return \sleep($s);
        }

        self::$now += (int) $s;

        return 0;
    }

    public static function usleep($us): void
    {
        if (null === self::$now) {
            \usleep($us);
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

        if ($asFloat) {
            return self::$now;
        }

        return \sprintf('%0.6f00 %d', self::$now - (int) self::$now, (int) self::$now);
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
        $ns = (self::$now - (int) self::$now) * 1000000000;

        if ($asNumber) {
            $number = \sprintf('%d%d', (int) self::$now, $ns);

            return \PHP_INT_SIZE === 8 ? (int) $number : (float) $number;
        }

        return [(int) self::$now, (int) $ns];
    }

    public static function register($class): void
    {
        $self = static::class;

        $mockedNs = [substr($class, 0, strrpos($class, '\\'))];
        if (0 < strpos($class, '\\Tests\\')) {
            $ns = str_replace('\\Tests\\', '\\', $class);
            $mockedNs[] = substr($ns, 0, strrpos($ns, '\\'));
        } elseif (0 === strpos($class, 'Tests\\')) {
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
EOPHP
            );
        }
    }
}
