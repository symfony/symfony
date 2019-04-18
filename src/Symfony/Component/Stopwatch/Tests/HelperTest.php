<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Helper;

class HelperTest extends TestCase
{
    /**
     * @dataProvider provideFormattedTime
     */
    public function testFormatTime($time, $formattedTime)
    {
        $this->assertSame(Helper::formatTime($time), $formattedTime);
    }

    public function provideFormattedTime()
    {
        yield [2, '2s'];
        yield [58, '58s'];
        yield [776, '12m 56s'];
        yield [3752, '1h 2m 32s'];
        yield [122817, '34h 6m 57s'];
    }
}
