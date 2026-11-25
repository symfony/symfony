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
use Symfony\Component\HtmlRenderer\Animation\Animation;

class AnimationTest extends TestCase
{
    public function testDefinitionsUseExplicitElapsedTimeAndPreserveNames()
    {
        $animations = Animation::listFromDeclarations(['animation' => 'Pulse calc(1s + 1000ms) 100ms linear 2 alternate both, Pulse 1s linear']);
        self::assertSame(['Pulse#0', 'Pulse#1'], array_keys($animations));
        $animation = $animations['Pulse#0'];
        self::assertSame(2.0, $animation->duration);
        self::assertSame(0.0, $animation->progress(0.0));
        self::assertEqualsWithDelta(0.25, $animation->progress(0.6), 0.00001);
        self::assertEqualsWithDelta(0.75, $animation->progress(2.6), 0.00001);
        self::assertEqualsWithDelta(0.0, $animation->progress(4.2), 0.00001);
        self::assertFalse($animation->isRunning(4.2));
    }

    public function testCoreTimingPreservesSmoothProgress()
    {
        self::assertSame(0.123, Animation::ease(Animation::timing('linear'), 0.123));
        self::assertSame(0.0, Animation::ease(Animation::timing('steps(4, end)'), 0.123));
    }

    public function testSharedKeyframeTraversalSupportsMissingEdgesAndComposition()
    {
        $between = static fn (string $from, string $to, float $t): string => (string) ((float) $from + ((float) $to - (float) $from) * $t);
        $animation = Animation::fromDeclarations(['animation' => 'fade 2s linear both']);
        self::assertSame(['opacity' => '0.5'], $animation->valuesAt([[1.0, ['opacity' => '0']]], 0.5, [], $between));
        $animation = Animation::fromDeclarations(['animation' => 'fade 2s linear both', 'animation-composition' => 'add']);
        self::assertSame(['opacity' => '4'], $animation->valuesAt([[0.0, ['opacity' => '1']], [1.0, ['opacity' => '3']]], 0.5, ['opacity' => '2'], $between, static fn (string $a, string $b): string => (string) ((float) $a + (float) $b)));
    }

    public function testScrollProgressAndRangeRemainIndependentOfInputCapture()
    {
        $animation = Animation::fromDeclarations(['animation-name' => 'fade', 'animation-timeline' => 'scroll()', 'animation-timing-function' => 'linear', 'animation-range' => 'entry 10% exit 90%', 'animation-fill-mode' => 'both']);
        self::assertTrue($animation->isScrollDriven());
        self::assertSame([['entry', 10.0], ['exit', 90.0]], $animation->range);
        self::assertSame(0.375, $animation->progressAt(0.375));
        self::assertSame(0.0, $animation->progressAt(-0.1));
        self::assertSame(1.0, $animation->progressAt(1.1));
    }
}
