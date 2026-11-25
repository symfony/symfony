<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Form;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Form\NumberStepper;

final class NumberStepperTest extends TestCase
{
    public function testRangeValuesSnapFromMinimumWithoutExceedingLastStep()
    {
        self::assertSame(0.35, NumberStepper::constrain(0.32, 0.15, 0.49, 0.1));
        self::assertSame(0.45, NumberStepper::constrain(1, 0.15, 0.49, 0.1));
        self::assertSame(0.3, NumberStepper::constrain(0.3, 0, 0.3, 0.1));
        self::assertSame(0.15, NumberStepper::constrain(-1, 0.15, 0.49, 0.1));
    }

    public function testFiniteExtremeBoundsDoNotProduceInfiniteStepLoops()
    {
        self::assertSame(1.0E308, NumberStepper::constrain(1.0E308, -1.0E308, 1.0E308, 0.1));
    }
}
