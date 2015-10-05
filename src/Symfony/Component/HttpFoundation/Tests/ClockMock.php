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

function time()
{
    return Tests\time();
}

namespace Symfony\Component\HttpFoundation\Tests;

function with_clock_mock($enable = null)
{
    static $enabled;

    if (null === $enable) {
        return $enabled;
    }

    $enabled = $enable;
}

function time()
{
    if (!with_clock_mock()) {
        return \time();
    }

    return $_SERVER['REQUEST_TIME'];
}
