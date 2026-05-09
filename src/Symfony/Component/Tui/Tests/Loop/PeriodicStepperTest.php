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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Loop\PeriodicStepper;

class PeriodicStepperTest extends TestCase
{
    public function testConstructorRejectsInvalidInterval()
    {
        $this->expectException(InvalidArgumentException::class);
        new PeriodicStepper(0.0);
    }

    public function testEveryMsRejectsInvalidInterval()
    {
        $this->expectException(InvalidArgumentException::class);
        PeriodicStepper::everyMs(0);
    }

    public function testAdvanceAccumulatesElapsedDelta()
    {
        $stepper = new PeriodicStepper(0.1, 5);

        $this->assertSame(0, $stepper->advance(0.05));
        $this->assertSame(1, $stepper->advance(0.05));
    }

    public function testAdvanceCapsLargeDelta()
    {
        $stepper = new PeriodicStepper(0.1, 3);

        $this->assertSame(3, $stepper->advance(1.0));
    }

    public function testSetIntervalResetsAccumulatedState()
    {
        $stepper = new PeriodicStepper(0.1, 5);
        $stepper->advance(0.05);

        $stepper->setIntervalSeconds(0.2);

        $this->assertSame(0, $stepper->advance(0.1));
        $this->assertSame(1, $stepper->advance(0.1));
    }
}
