<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Animation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\Easing;

final class EasingTest extends TestCase
{
    public function testSmoothEasingPreservesFractionalProgress()
    {
        self::assertSame(0.123456, Easing::evaluate(Easing::parse('linear'), 0.123456));
        self::assertEqualsWithDelta(0.5, Easing::evaluate(Easing::parse('cubic-bezier(0, 0, 1, 1)'), 0.5), 0.0000001);
    }

    public function testStepAndPiecewiseFunctionsRetainCssBehavior()
    {
        self::assertSame(0.25, Easing::evaluate(Easing::parse('steps(4, end)'), 0.3));
        self::assertSame(0.5, Easing::evaluate(Easing::parse('linear(0, 1 50%, 0)'), 0.25));
        self::assertNull(Easing::parse('steps(1, jump-none)'));
        self::assertNull(Easing::parse('cubic-bezier(2, 0, 1, 1)'));
    }
}
