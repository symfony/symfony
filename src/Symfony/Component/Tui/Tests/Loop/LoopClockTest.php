<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Loop;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Loop\LoopClock;

class LoopClockTest extends TestCase
{
    public function testAdvanceWithExplicitDeltaAdvancesInternalTime()
    {
        $clock = new LoopClock(100.0);

        $elapsed = $clock->advance(0.25);

        $this->assertSame(0.25, $elapsed);
        $this->assertSame(100.25, $clock->now());
    }

    public function testAdvanceWithoutDeltaUsesWallClockElapsedTime()
    {
        $clock = new LoopClock();
        usleep(1000);

        $elapsed = $clock->advance();

        $this->assertGreaterThan(0.0, $elapsed);
    }

    public function testResetSetsCurrentTime()
    {
        $clock = new LoopClock(100.0);
        $clock->reset(42.0);

        $this->assertSame(42.0, $clock->now());
    }
}
