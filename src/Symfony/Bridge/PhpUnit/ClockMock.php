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
 */
class ClockMock
{
    private static $now;

    public static function withClockMock($enable = null)
    {
        if (null === $enable) {
            return null !== self::$now;
        }

        self::$now = is_numeric($enable) ? (float) $enable : ($enable ? microtime(true) : null);
    }

    public static function time()
    {
        if (null === self::$now) {
            return \time();
        }

        return (int) self::$now;
    }

    public static function sleep($s)
    {
        if (null === self::$now) {
            return \sleep($s);
        }

        self::$now += (int) $s;

        return 0;
    }

    public static function usleep($us)
    {
        if (null === self::$now) {
            return \usleep($us);
        }

        self::$now += $us / 1000000;
    }

    public static function microtime($asFloat = false)
    {
        if (null === self::$now) {
            return \microtime($asFloat);
        }

        if ($asFloat) {
            return self::$now;
        }

        return sprintf('%0.6f00 %d', self::$now - (int) self::$now, (int) self::$now);
    }

    public static function register($class)
    {
        $self = \get_called_class();

        $mockedNs = array(substr($class, 0, strrpos($class, '\\')));
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
    return \\$self::usleep(\$us);
}

EOPHP
            );
        }
    }
}
