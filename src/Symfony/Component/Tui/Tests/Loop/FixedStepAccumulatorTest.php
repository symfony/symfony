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

    /**
     * The cap bounds the work one update may do; the time above it is time
     * that was missed, not work owed.
     */
    public function testTimeAboveTheCapIsDroppedRatherThanQueued()
    {
        $accumulator = new FixedStepAccumulator(10.0, 5);

        // Ten seconds went by in one update: a hundred steps' worth.
        $this->assertSame(5, $accumulator->computeSteps(10.0));

        // The updates that follow run at their own rate again.
        for ($i = 0; $i < 20; ++$i) {
            $this->assertSame(1, $accumulator->computeSteps(0.1), 'Update '.$i.' is not paying back the stall.');
        }
    }

    public function testTheFractionSurvivesADroppedBacklog()
    {
        $accumulator = new FixedStepAccumulator(10.0, 5);

        $accumulator->computeSteps(10.05);

        // 0.5 of a step was left over, so the next half step completes it.
        $this->assertSame(1, $accumulator->computeSteps(0.05));
    }

    public function testTheRejectedStepRateIsReported()
    {
        $this->expectExceptionMessage('Steps per second must be greater than 0, got "-0.5".');

        new FixedStepAccumulator(-0.5);
    }
}
