<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

function time($asFloat = false)
{
    return Tests\time();
}

namespace Symfony\Component\HttpFoundation\Tests;

function enable_clock_mock()
{
    global $clockMockEnabled;
    $clockMockEnabled = true;
}

function disable_clock_mock()
{
    global $clockMockEnabled;
    $clockMockEnabled = 0;
}

function time()
{
    global $clockMockEnabled;

    if ($clockMockEnabled) {
        return $_SERVER['REQUEST_TIME'];
    } else {
        return \time();
    }
}
