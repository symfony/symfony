<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch;

function microtime($asFloat = false)
{
    return Tests\microtime($asFloat);
}

namespace Symfony\Component\Stopwatch\Tests;

function enable_clock_mock()
{
    $GLOBALS['stopwatch_clock_mock_enabled'] = true;
}

function disable_clock_mock()
{
    $GLOBALS['stopwatch_clock_mock_enabled'] = false;
}

function usleep($us)
{
    static $now;

    if (!$GLOBALS['stopwatch_clock_mock_enabled']) {
        \usleep($us);

        return;
    }

    if (null === $now) {
        $now = \microtime(true);
    }

    return $now += $us / 1000000;
}

function microtime($asFloat = false)
{
    if (!isset($GLOBALS['stopwatch_clock_mock_enabled']) || !$GLOBALS['stopwatch_clock_mock_enabled']) {
        return \microtime($asFloat);
    }

    if (!$asFloat) {
        return \microtime(false);
    }

    return usleep(1);
}
