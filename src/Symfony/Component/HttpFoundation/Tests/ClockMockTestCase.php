<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundationSession\Tests\Storage\Handler;

use Symfony\Component\HttpFoundation\Tests;

function time()
{
    return Tests\time();
}

namespace Symfony\Component\HttpFoundationSession\Storage\Handler;

use Symfony\Component\HttpFoundation\Tests;

function time()
{
    return Tests\time();
}

namespace Symfony\Component\HttpFoundationSession\Tests\Storage;

use Symfony\Component\HttpFoundation\Tests;

function time()
{
    return Tests\time();
}

namespace Symfony\Component\HttpFoundationSession\Storage;

use Symfony\Component\HttpFoundation\Tests;

function time()
{
    return Tests\time();
}

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

class ClockMockTestCase extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        with_clock_mock(true);
    }

    protected function tearDown()
    {
        with_clock_mock(false);
    }
}
