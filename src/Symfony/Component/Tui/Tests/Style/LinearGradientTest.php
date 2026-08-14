<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Style\Angle;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorStop;
use Symfony\Component\Tui\Style\GradientDirection;
use Symfony\Component\Tui\Style\LinearGradient;

final class LinearGradientTest extends TestCase
{
    public function testResolveTwoColorsHorizontal()
    {
        $g = LinearGradient::from(['#000000', '#ffffff'], GradientDirection::LeftToRight);
        $codes = self::codes($g->resolve(3, 1));

        // col 0 = black, col 2 = white, col 1 = mid-grey
        $this->assertSame(Color::from('#000000')->toBackgroundCode(), $codes[0][0]);
        $this->assertSame(Color::from('#ffffff')->toBackgroundCode(), $codes[0][2]);

        // Mid color is grey (128,128,128)
        $mid = Color::rgb(128, 128, 128)->toBackgroundCode();
        $this->assertSame($mid, $codes[0][1]);
    }

    public function testResolveAllRowsIdenticalForHorizontal()
    {
        $g = LinearGradient::from(['#ff0000', '#0000ff'], GradientDirection::LeftToRight);
        $codes = self::codes($g->resolve(4, 3));

        $this->assertSame($codes[0], $codes[1]);
        $this->assertSame($codes[0], $codes[2]);
    }

    public function testResolveAllColsIdenticalForVertical()
    {
        $g = LinearGradient::from(['#ff0000', '#0000ff'], GradientDirection::TopToBottom);
        $codes = self::codes($g->resolve(4, 3));

        $this->assertSame($codes[0][0], $codes[0][1]);
        $this->assertSame($codes[0][0], $codes[0][2]);
        $this->assertSame($codes[0][0], $codes[0][3]);

        // Vertical: different rows must have different codes
        $this->assertNotSame($codes[0][0], $codes[1][0]);
        $this->assertNotSame($codes[1][0], $codes[2][0]);
    }

    public function testResolveRightToLeftIsReversedLeftToRight()
    {
        $g1 = LinearGradient::from(['#000000', '#ffffff'], GradientDirection::LeftToRight);
        $g2 = LinearGradient::from(['#000000', '#ffffff'], GradientDirection::RightToLeft);

        $c1 = self::codes($g1->resolve(4, 1));
        $c2 = self::codes($g2->resolve(4, 1));

        $this->assertSame($c1[0][0], $c2[0][3]);
        $this->assertSame($c1[0][3], $c2[0][0]);

        $this->assertSame($c1[0][1], $c2[0][2]);
        $this->assertSame($c1[0][2], $c2[0][1]);
    }

    public function testResolveThreeColorsEvenlyDistributed()
    {
        $g = LinearGradient::from(['#000000', '#ffffff', '#000000'], GradientDirection::LeftToRight);
        $codes = self::codes($g->resolve(5, 1));

        // col 0 = black, col 2 = white, col 4 = black
        $this->assertSame(Color::from('#000000')->toBackgroundCode(), $codes[0][0]);
        $this->assertSame(Color::from('#ffffff')->toBackgroundCode(), $codes[0][2]);
        $this->assertSame(Color::from('#000000')->toBackgroundCode(), $codes[0][4]);
    }

    public function testResolveIsStableForAGivenSize()
    {
        // PHP arrays compare by value, so this asserts the result is stable, not that
        // the cache was hit. testResolveKeepsOnlyTheLastSize covers the cache itself.
        $g = LinearGradient::from(['#ff0000', '#0000ff'], GradientDirection::LeftToRight);

        $a = self::codes($g->resolve(10, 5));
        $b = self::codes($g->resolve(10, 5));

        $this->assertSame($a, $b);
    }

    public function testResolveKeepsOnlyTheLastSize()
    {
        $g = LinearGradient::from(['#000000', '#ffffff']);
        $g->resolve(1, 24);

        $before = memory_get_usage();
        for ($width = 2; $width <= 300; ++$width) {
            $g->resolve($width, 24);
        }
        $retained = memory_get_usage() - $before;

        // Keeping every size cost around 91 MB here; one strip is well under 5.
        $this->assertLessThan(5 * 1024 * 1024, $retained,
            \sprintf('the cache must not grow with every size, %.1f MB retained', $retained / 1048576));
    }

    public function testResolveRecomputesASizeItHasEvicted()
    {
        $g = LinearGradient::from(['#000000', '#ffffff']);

        $first = self::codes($g->resolve(10, 5));
        $g->resolve(20, 5);

        $this->assertSame($first, self::codes($g->resolve(10, 5)), 'an evicted size must be recomputed identically');
    }

    public function testFromAcceptsColorObjects()
    {
        $g = LinearGradient::from([Color::named('red'), Color::named('blue')], GradientDirection::LeftToRight);
        $codes = self::codes($g->resolve(2, 1));

        // Named colors are normalized to RGB so all gradient steps produce consistent \x1b[48;2;...] codes
        $this->assertSame(Color::named('red')->mix(Color::named('blue'), 0)->toBackgroundCode(), $codes[0][0]);
        $this->assertSame(Color::named('red')->mix(Color::named('blue'), 100)->toBackgroundCode(), $codes[0][1]);
    }

    public function testFromAcceptsMixedInput()
    {
        // string, Color object, palette int: all accepted as gradient stops
        $g = LinearGradient::from(['red', Color::named('blue'), 196], GradientDirection::LeftToRight);
        $codes = self::codes($g->resolve(3, 1));

        // All codes must be RGB format: named/palette stops are normalized via mix()
        foreach ($codes[0] as $col => $code) {
            $this->assertStringStartsWith("\x1b[48;2;", $code,
                "Column $col must produce an RGB bg code");
        }
    }

    public function testEveryDirectionMapsToItsAxis()
    {
        $lr = LinearGradient::from(['red', 'blue'], GradientDirection::LeftToRight);
        $rl = LinearGradient::from(['red', 'blue'], GradientDirection::RightToLeft);
        $tb = LinearGradient::from(['red', 'blue'], GradientDirection::TopToBottom);
        $bt = LinearGradient::from(['red', 'blue'], GradientDirection::BottomToTop);

        $codesLr = self::codes($lr->resolve(3, 1));
        $codesRl = self::codes($rl->resolve(3, 1));
        $codesTb = self::codes($tb->resolve(1, 3));
        $codesBt = self::codes($bt->resolve(1, 3));

        $this->assertSame($codesLr[0][0], $codesRl[0][2]);
        $this->assertSame($codesTb[0][0], $codesBt[2][0]);
    }

    public function testFromAcceptsAngleDegrees()
    {
        // Angle::degrees(0) = left-to-right: first column gets first color, last column gets last color
        $g = LinearGradient::from(['#ff0000', '#0000ff'], Angle::degrees(0));
        $ref = LinearGradient::from(['#ff0000', '#0000ff']);
        $this->assertSame(self::codes($ref->resolve(5, 1))[0][0], self::codes($g->resolve(5, 1))[0][0]);
        $this->assertSame(self::codes($ref->resolve(5, 1))[0][4], self::codes($g->resolve(5, 1))[0][4]);

        // Angle::degrees(90) = top-to-bottom
        $g90 = LinearGradient::from(['#ff0000', '#0000ff'], Angle::degrees(90));
        $ref90 = LinearGradient::from(['#ff0000', '#0000ff'], GradientDirection::TopToBottom);
        $this->assertSame(self::codes($ref90->resolve(1, 5))[0][0], self::codes($g90->resolve(1, 5))[0][0]);
        $this->assertSame(self::codes($ref90->resolve(1, 5))[4][0], self::codes($g90->resolve(1, 5))[4][0]);
    }

    public function testFromAcceptsAngleRadians()
    {
        // radians(π/2) == degrees(90) == top-to-bottom
        $gDeg = LinearGradient::from(['#ff0000', '#0000ff'], Angle::degrees(90));
        $gRad = LinearGradient::from(['#ff0000', '#0000ff'], Angle::radians(\M_PI / 2));
        $this->assertSame(self::codes($gDeg->resolve(3, 4)), self::codes($gRad->resolve(3, 4)));
    }

    public function testAngle45ProducesDiagonalGradient()
    {
        // At 45°, the gradient runs diagonally: top-left = first color, bottom-right = last color
        $g = LinearGradient::from(['#ff0000', '#0000ff'], Angle::degrees(45));
        $codes = self::codes($g->resolve(3, 3));

        $firstColor = Color::from('#ff0000')->mix(Color::from('#0000ff'), 0)->toBackgroundCode();
        $lastColor = Color::from('#ff0000')->mix(Color::from('#0000ff'), 100)->toBackgroundCode();

        $this->assertSame($firstColor, $codes[0][0], 'top-left must be first color at 45°');
        $this->assertSame($lastColor, $codes[2][2], 'bottom-right must be last color at 45°');

        // Corners orthogonal to the gradient axis must share the midpoint color
        $this->assertSame($codes[0][2], $codes[2][0], 'anti-diagonal corners must be equal at 45°');
    }

    public function testAngle135ProducesMirroredDiagonal()
    {
        // At 135° the gradient runs top-right to bottom-left (opposite diagonal)
        $g = LinearGradient::from(['#ff0000', '#0000ff'], Angle::degrees(135));
        $codes = self::codes($g->resolve(3, 3));

        $firstColor = Color::from('#ff0000')->mix(Color::from('#0000ff'), 0)->toBackgroundCode();
        $lastColor = Color::from('#ff0000')->mix(Color::from('#0000ff'), 100)->toBackgroundCode();

        $this->assertSame($firstColor, $codes[0][2], 'top-right must be first color at 135°');
        $this->assertSame($lastColor, $codes[2][0], 'bottom-left must be last color at 135°');
    }

    public function testNamedColorEndpointProducesConsistentRgbCode()
    {
        // At a gradient endpoint with a named color stop, the resolved code
        // must be an RGB code (\x1b[48;2;...]) matching the format produced
        // by intermediate rows via mix(). Without this, named colors produce
        // ANSI codes (\x1b[41m] for red) that terminals render differently
        // from the adjacent RGB-mixed rows, creating a visible lighter band.
        $g = LinearGradient::from(['#0000ff', 'red'], GradientDirection::TopToBottom);
        $codes = self::codes($g->resolve(1, 3)); // 3 rows: blue at top, mix in middle, red at bottom

        foreach ($codes as $row => $rowCodes) {
            $this->assertStringStartsWith("\x1b[48;2;", $rowCodes[0],
                "Row $row must produce an RGB bg code for visual consistency across all gradient steps");
        }
    }

    public function testColorStopShiftsTransitionPoint()
    {
        // Without stop: red→blue uniform over 5 cols, midpoint at col 2
        // With stop at 20%: green is at col 1 (offset=0.25 for width=5 → position=25 > stop=20)
        // Key property: with green at 20%, col 0 (offset=0) = red, col 1 (offset=0.25) is past the stop → in green→blue segment
        $g = LinearGradient::from([
            ColorStop::at('#ff0000', 0),
            ColorStop::at('#00ff00', 20),
            ColorStop::at('#0000ff', 100),
        ]);
        $codes = self::codes($g->resolve(5, 1));

        // col 0 → offset=0 → red (mix red→green at 0%)
        $this->assertSame(
            Color::from('#ff0000')->mix(Color::from('#00ff00'), 0)->toBackgroundCode(),
            $codes[0][0]
        );
        // col 4 → offset=1 → blue (mix green→blue at 100%)
        $this->assertSame(
            Color::from('#00ff00')->mix(Color::from('#0000ff'), 100)->toBackgroundCode(),
            $codes[0][4]
        );
        // col 1 → offset=0.25 → past green stop (20%) → in green→blue segment at localOffset=(25-20)/80=6.25%
        // The ratio stays in float, so this is 6.25% of the way, not a rounded 6%.
        $this->assertSame(
            Color::rgb(0, (int) round(255 * (1 - 0.0625)), (int) round(255 * 0.0625))->toBackgroundCode(),
            $codes[0][1]
        );
    }

    public function testInterpolationIsNotCappedAtOneHundredAndOneColors()
    {
        // Color::mix() takes an integer percentage, which capped a two-stop
        // gradient at 101 distinct colors per segment and made wide gradients band.
        $codes = self::codes(LinearGradient::from(['#000000', '#ffffff'])->resolve(240, 1));

        $this->assertGreaterThan(101, \count(array_unique($codes[0])));
    }

    public function testEveryStripIsMonotonicAcrossItsWidth()
    {
        // A greyscale ramp must never step backwards from one column to the next.
        $codes = self::codes(LinearGradient::from(['#000000', '#ffffff'])->resolve(200, 1));

        $previous = -1;
        foreach ($codes[0] as $col => $code) {
            $this->assertSame(1, preg_match('/48;2;(\d+);/', $code, $m), "column $col must emit a truecolor bg code");
            $this->assertGreaterThanOrEqual($previous, (int) $m[1], "column $col must not step back");
            $previous = (int) $m[1];
        }

        $this->assertSame(255, $previous, 'the last column must reach the end color');
    }

    public function testHardStopProducesSharpChange()
    {
        // Two stops at the same position → sharp color change, no blend
        $g = LinearGradient::from([
            ColorStop::at('#ff0000', 0),
            ColorStop::at('#ff0000', 50),
            ColorStop::at('#0000ff', 50),
            ColorStop::at('#0000ff', 100),
        ]);
        $codes = self::codes($g->resolve(5, 1));

        $red = Color::from('#ff0000')->mix(Color::from('#ff0000'), 0)->toBackgroundCode();
        $blue = Color::from('#0000ff')->mix(Color::from('#0000ff'), 0)->toBackgroundCode();

        // cols 0-1 (offset=0,0.25) → red side
        $this->assertSame($red, $codes[0][0]);
        $this->assertSame($red, $codes[0][1]);
        // cols 3-4 (offset=0.75,1) → blue side
        $this->assertSame($blue, $codes[0][3]);
        $this->assertSame($blue, $codes[0][4]);
    }

    public function testMixedPlainAndColorStopBackwardCompat()
    {
        // Plain colors still work (uniform distribution)
        $g1 = LinearGradient::from(['#ff0000', '#0000ff']);
        $g2 = LinearGradient::from([
            ColorStop::at('#ff0000', 0),
            ColorStop::at('#0000ff', 100),
        ]);
        $this->assertSame(self::codes($g1->resolve(4, 1)), self::codes($g2->resolve(4, 1)));
    }

    public function testFromRequiresAtLeastTwoColors()
    {
        $this->expectException(\InvalidArgumentException::class);
        LinearGradient::from(['red'], GradientDirection::LeftToRight);
    }

    public function testResolveWithSingleCellReturnsFirstColor()
    {
        $g = LinearGradient::from(['#ff0000', '#0000ff'], GradientDirection::LeftToRight);
        $codes = self::codes($g->resolve(1, 1));

        $this->assertSame(Color::from('#ff0000')->toBackgroundCode(), $codes[0][0]);
    }

    public function testFromExistingGradientWithoutDirectionReturnsSameInstance()
    {
        $g = LinearGradient::from(['#000000', '#ffffff'], GradientDirection::TopToBottom);

        $this->assertSame($g, LinearGradient::from($g));
    }

    public function testFromExistingGradientOverridesDirection()
    {
        $g = LinearGradient::from(['#000000', '#ffffff']);
        $rotated = LinearGradient::from($g, GradientDirection::TopToBottom);

        $this->assertNotSame($g, $rotated);
        $this->assertSame(
            self::codes(LinearGradient::from(['#000000', '#ffffff'], GradientDirection::TopToBottom)->resolve(4, 3)),
            self::codes($rotated->resolve(4, 3)),
            'stops must be preserved and the new direction applied'
        );
    }

    public function testFromExistingGradientAcceptsAngle()
    {
        $g = LinearGradient::from(['#000000', '#ffffff']);
        $rotated = LinearGradient::from($g, Angle::degrees(90));

        $this->assertSame(
            self::codes(LinearGradient::from(['#000000', '#ffffff'], Angle::degrees(90))->resolve(4, 3)),
            self::codes($rotated->resolve(4, 3))
        );
    }

    public function testFromExistingGradientLeavesTheSourceUntouched()
    {
        $g = LinearGradient::from(['#000000', '#ffffff']);
        $before = self::codes($g->resolve(4, 3));

        LinearGradient::from($g, GradientDirection::TopToBottom);

        $this->assertSame($before, self::codes($g->resolve(4, 3)));
    }

    public function testFromExistingGradientPreservesColorStopOffsets()
    {
        $stops = [ColorStop::at('#000000', 0), ColorStop::at('#ffffff', 25)];
        $rotated = LinearGradient::from(LinearGradient::from($stops, GradientDirection::LeftToRight), GradientDirection::TopToBottom);

        $this->assertSame(
            self::codes(LinearGradient::from($stops, GradientDirection::TopToBottom)->resolve(5, 5)),
            self::codes($rotated->resolve(5, 5))
        );
    }

    public function testFromArrayWithoutDirectionDefaultsToLeftToRight()
    {
        $this->assertSame(
            self::codes(LinearGradient::from(['#000000', '#ffffff'], GradientDirection::LeftToRight)->resolve(4, 3)),
            self::codes(LinearGradient::from(['#000000', '#ffffff'])->resolve(4, 3))
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function codes(array $colors): array
    {
        return array_map(static fn (array $row) => array_map(static fn (Color $c) => $c->toBackgroundCode(), $row), $colors);
    }
}
