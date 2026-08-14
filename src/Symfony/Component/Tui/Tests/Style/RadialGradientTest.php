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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\RadialGradient;

final class RadialGradientTest extends TestCase
{
    public function testCenterCellGetsFirstColor()
    {
        // A 5×5 grid centered at (0.5, 0.5): cell (2,2) is the center, offset=0 → first color
        $g = RadialGradient::from(['#ff0000', '#0000ff']);
        $codes = $g->resolve(5, 5);

        $firstColor = Color::from('#ff0000')->mix(Color::from('#0000ff'), 0)->toBackgroundCode();
        $this->assertSame($firstColor, $codes[2][2], 'center cell must receive first color (offset=0)');
    }

    public function testFarthestCornerGetsLastColor()
    {
        // In a centered 5×5 grid all four corners are equidistant → offset=1 → last color
        $g = RadialGradient::from(['#ff0000', '#0000ff']);
        $codes = $g->resolve(5, 5);

        $lastColor = Color::from('#ff0000')->mix(Color::from('#0000ff'), 100)->toBackgroundCode();
        $this->assertSame($lastColor, $codes[0][0], 'top-left corner must receive last color (offset=1)');
        $this->assertSame($lastColor, $codes[0][4], 'top-right corner must receive last color (offset=1)');
        $this->assertSame($lastColor, $codes[4][0], 'bottom-left corner must receive last color (offset=1)');
        $this->assertSame($lastColor, $codes[4][4], 'bottom-right corner must receive last color (offset=1)');
    }

    public function testCornersAreSymmetricAroundCenter()
    {
        // All four corners of a centered grid are equidistant regardless of aspect ratio
        $g = RadialGradient::from(['#ff0000', '#0000ff']);
        $codes = $g->resolve(5, 5);

        $this->assertSame($codes[0][0], $codes[0][4]);
        $this->assertSame($codes[0][0], $codes[4][0]);
        $this->assertSame($codes[0][0], $codes[4][4]);

        // Left/right symmetry: cells equidistant horizontally get the same color
        $this->assertSame($codes[2][0], $codes[2][4]);
        // Top/bottom symmetry: cells equidistant vertically get the same color
        $this->assertSame($codes[0][2], $codes[4][2]);
    }

    public function testAspectRatioMakesCirclesVisual()
    {
        // With aspect ratio 2.0 (standard terminals: cells ~2× taller than wide),
        // 2 columns of offset must equal 1 row of offset in visual distance.
        // 5×3 grid centered at (col=2, row=1) is a clean case.
        $g = RadialGradient::from(['#ff0000', '#0000ff'], 0.5, 0.5, 2.0);
        $codes = $g->resolve(5, 3);

        // 2 cols right of center == 1 row below center in corrected distance
        $this->assertSame($codes[1][4], $codes[2][2],
            'with ar=2.0, 2 columns right must equal 1 row down in visual distance');
        // 2 cols left == 1 row above
        $this->assertSame($codes[1][0], $codes[0][2],
            'with ar=2.0, 2 columns left must equal 1 row up in visual distance');
    }

    public function testAspectRatio1ProducesSymmetricEdgeMidpoints()
    {
        // With ar=1.0 (square cells), horizontal and vertical edge midpoints
        // of a square grid are equidistant from center
        $g = RadialGradient::from(['#ff0000', '#0000ff'], 0.5, 0.5, 1.0);
        $codes = $g->resolve(5, 5);

        // All four edge midpoints equidistant at d=2
        $this->assertSame($codes[0][2], $codes[2][0]);
        $this->assertSame($codes[0][2], $codes[2][4]);
        $this->assertSame($codes[0][2], $codes[4][2]);
    }

    public function testCustomCenterAtTopLeft()
    {
        // Center at (0, 0): top-left cell (0,0) is the origin → offset=0 → first color
        $g = RadialGradient::from(['#ff0000', '#0000ff'], 0.0, 0.0);
        $codes = $g->resolve(3, 3);

        $firstColor = Color::from('#ff0000')->mix(Color::from('#0000ff'), 0)->toBackgroundCode();
        $this->assertSame($firstColor, $codes[0][0], 'origin cell must get first color');

        // Bottom-right corner is the farthest → last color
        $lastColor = Color::from('#ff0000')->mix(Color::from('#0000ff'), 100)->toBackgroundCode();
        $this->assertSame($lastColor, $codes[2][2], 'farthest corner must get last color');
    }

    public function testCenteredFactoryIsEquivalentToFromWithCenter()
    {
        $g1 = RadialGradient::centered('#ff0000', '#0000ff');
        $g2 = RadialGradient::from(['#ff0000', '#0000ff'], 0.5, 0.5);

        $this->assertSame($g1->resolve(6, 4), $g2->resolve(6, 4));
    }

    public function testFromRequiresAtLeastTwoColors()
    {
        $this->expectException(\InvalidArgumentException::class);
        RadialGradient::from(['red']);
    }

    #[DataProvider('invalidAspectRatioProvider')]
    public function testFromRejectsAnUnusableAspectRatio(float $aspectRatio)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('aspect ratio of a radial gradient must be a finite number greater than 0');

        RadialGradient::from(['#ff0000', '#0000ff'], 0.5, 0.5, $aspectRatio);
    }

    public static function invalidAspectRatioProvider(): iterable
    {
        yield 'zero flattens the vertical axis' => [0.0];
        yield 'negative mirrors the distance' => [-2.0];
        yield 'NAN propagates to every cell' => [\NAN];
        yield 'INF collapses the gradient' => [\INF];
    }

    #[DataProvider('nonFiniteCenterProvider')]
    public function testFromRejectsANonFiniteCenter(float $cx, float $cy)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('center of a radial gradient must be finite');

        RadialGradient::from(['#ff0000', '#0000ff'], $cx, $cy);
    }

    public static function nonFiniteCenterProvider(): iterable
    {
        yield 'NAN on x' => [\NAN, 0.5];
        yield 'NAN on y' => [0.5, \NAN];
        yield 'INF on x' => [\INF, 0.5];
    }

    public function testFromRejectsAnUnusableAspectRatioOnAnExistingGradient()
    {
        $this->expectException(InvalidArgumentException::class);

        RadialGradient::from(RadialGradient::centered('#ff0000', '#0000ff'), null, null, 0.0);
    }

    public function testFromAcceptsACenterOutsideTheArea()
    {
        // CSS allows a center outside the box; only non-finite values are rejected.
        $codes = RadialGradient::from(['#ff0000', '#0000ff'], 5.0, -3.0)->resolve(4, 3);

        $this->assertCount(3, $codes);
        $this->assertCount(4, $codes[0]);
    }

    public function testFromExistingGradientWithoutGeometryReturnsSameInstance()
    {
        $g = RadialGradient::from(['#ff0000', '#0000ff'], 0.2, 0.3, 1.0);

        $this->assertSame($g, RadialGradient::from($g));
    }

    public function testFromExistingGradientOverridesCenter()
    {
        $g = RadialGradient::centered('#ff0000', '#0000ff');
        $moved = RadialGradient::from($g, 0.0, 0.0);

        $this->assertNotSame($g, $moved);
        $this->assertSame(
            RadialGradient::from(['#ff0000', '#0000ff'], 0.0, 0.0)->resolve(5, 5),
            $moved->resolve(5, 5),
            'stops must be preserved and the new center applied'
        );
    }

    public function testFromExistingGradientKeepsUnspecifiedGeometry()
    {
        $g = RadialGradient::from(['#ff0000', '#0000ff'], 0.2, 0.3, 1.0);
        $stretched = RadialGradient::from($g, null, null, 3.0);

        $this->assertSame(
            RadialGradient::from(['#ff0000', '#0000ff'], 0.2, 0.3, 3.0)->resolve(6, 4),
            $stretched->resolve(6, 4),
            '$cx/$cy must be inherited when only $aspectRatio is given'
        );
    }

    public function testFromExistingGradientLeavesTheSourceUntouched()
    {
        $g = RadialGradient::centered('#ff0000', '#0000ff');
        $before = $g->resolve(5, 5);

        RadialGradient::from($g, 0.0, 0.0, 1.0);

        $this->assertSame($before, $g->resolve(5, 5));
    }

    public function testFromArrayWithoutGeometryUsesDefaults()
    {
        $this->assertSame(
            RadialGradient::from(['#ff0000', '#0000ff'], 0.5, 0.5, 2.0)->resolve(6, 4),
            RadialGradient::from(['#ff0000', '#0000ff'])->resolve(6, 4)
        );
    }

    public function testResolveIsStableForAGivenSize()
    {
        $g = RadialGradient::from(['#ff0000', '#0000ff']);

        $a = $g->resolve(8, 6);
        $b = $g->resolve(8, 6);

        $this->assertSame($a, $b);
    }

    public function testResolveKeepsOnlyTheLastSize()
    {
        $g = RadialGradient::centered('#000000', '#ffffff');
        $g->resolve(1, 24);

        $before = memory_get_usage();
        for ($width = 2; $width <= 300; ++$width) {
            $g->resolve($width, 24);
        }
        $retained = memory_get_usage() - $before;

        $this->assertLessThan(5 * 1024 * 1024, $retained,
            \sprintf('the cache must not grow with every size, %.1f MB retained', $retained / 1048576));
    }

    public function testResolveRecomputesASizeItHasEvicted()
    {
        $g = RadialGradient::centered('#000000', '#ffffff');

        $first = $g->resolve(8, 6);
        $g->resolve(16, 6);

        $this->assertSame($first, $g->resolve(8, 6), 'an evicted size must be recomputed identically');
    }

    public function testThreeColorsInterpolateCorrectly()
    {
        // With 3 colors: center = first, mid-radius = second, edge = third
        $g = RadialGradient::from(['#ff0000', '#00ff00', '#0000ff']);
        $codes = $g->resolve(5, 5);

        // Center (offset=0) → mix(red, green, 0) = red
        $firstColor = Color::from('#ff0000')->mix(Color::from('#00ff00'), 0)->toBackgroundCode();
        $this->assertSame($firstColor, $codes[2][2]);

        // Corners (offset=1) → mix(green, blue, 100) = blue
        $lastColor = Color::from('#00ff00')->mix(Color::from('#0000ff'), 100)->toBackgroundCode();
        $this->assertSame($lastColor, $codes[0][0]);
    }
}
