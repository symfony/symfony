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
    $GLOBALS['http_foundation_enable_clock_mock'] = true;
}

function disable_clock_mock()
{
    $GLOBALS['http_foundation_enable_clock_mock'] = false;
}

function time()
{
    if (!$GLOBALS['http_foundation_enable_clock_mock']) {
        return \time();
    }

    return $_SERVER['REQUEST_TIME'];
}
