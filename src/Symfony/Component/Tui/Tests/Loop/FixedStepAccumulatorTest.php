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
use Symfony\Component\Tui\Loop\FixedStepAccumulator;

class FixedStepAccumulatorTest extends TestCase
{
    public function testConstructorRejectsInvalidArguments()
    {
        $this->expectException(InvalidArgumentException::class);
        new FixedStepAccumulator(0.0);
    }

    public function testComputeStepsAccumulatesFractionalDelta()
    {
        $accumulator = new FixedStepAccumulator(60.0, 5);

        $this->assertSame(0, $accumulator->computeSteps(1.0 / 120.0));
        $this->assertSame(1, $accumulator->computeSteps(1.0 / 120.0));
    }

    public function testComputeStepsCapsLargeDelta()
    {
        $accumulator = new FixedStepAccumulator(60.0, 5);

        $this->assertSame(5, $accumulator->computeSteps(1.0));
    }

    public function testSetStepsPerSecondAffectsStepCount()
    {
        $accumulator = new FixedStepAccumulator(60.0, 5);
        $accumulator->setStepsPerSecond(30.0);

        $this->assertSame(0, $accumulator->computeSteps(1.0 / 60.0));
        $this->assertSame(1, $accumulator->computeSteps(1.0 / 60.0));
    }

    public function testResetClearsResidualAccumulation()
    {
        $accumulator = new FixedStepAccumulator(60.0, 5);
        $this->assertSame(0, $accumulator->computeSteps(1.0 / 120.0));

        $accumulator->reset();

        $this->assertSame(0, $accumulator->computeSteps(1.0 / 120.0));
    }
}
