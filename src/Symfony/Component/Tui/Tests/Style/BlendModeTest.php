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
use Symfony\Component\Tui\Style\BlendMode;

class BlendModeTest extends TestCase
{
    /**
     * A backdrop of (.2, .4, .8) and a source of (.6, .6, .2), the results computed with the formulas of the
     * Compositing specification.
     *
     * @param array{int, int, int} $expected
     */
    #[DataProvider('provideBlends')]
    public function testBlend(BlendMode $mode, array $expected)
    {
        $this->assertEqualsWithDelta($expected, $mode->blend([51, 102, 204], [153, 153, 51]), 1);
    }

    public static function provideBlends(): iterable
    {
        yield 'normal' => [BlendMode::Normal, [153, 153, 51]];
        yield 'multiply' => [BlendMode::Multiply, [31, 61, 41]];
        yield 'screen' => [BlendMode::Screen, [173, 194, 214]];
        yield 'overlay' => [BlendMode::Overlay, [61, 122, 173]];
        yield 'darken' => [BlendMode::Darken, [51, 102, 51]];
        yield 'lighten' => [BlendMode::Lighten, [153, 153, 204]];
        yield 'color-dodge' => [BlendMode::ColorDodge, [128, 255, 255]];
        yield 'color-burn' => [BlendMode::ColorBurn, [0, 0, 0]];
        yield 'hard-light' => [BlendMode::HardLight, [92, 133, 82]];
        yield 'soft-light' => [BlendMode::SoftLight, [64, 114, 180]];
        yield 'difference' => [BlendMode::Difference, [102, 51, 153]];
        yield 'exclusion' => [BlendMode::Exclusion, [143, 133, 173]];
        yield 'luminosity' => [BlendMode::Luminosity, [95, 146, 248]];
        yield 'color' => [BlendMode::Color, [109, 109, 7]];
        yield 'plus-lighter' => [BlendMode::PlusLighter, [204, 255, 255]];
        yield 'plus-darker' => [BlendMode::PlusDarker, [0, 0, 0]];
    }

    public function testNonSeparableModes()
    {
        // A gray source has no saturation: the backdrop loses its own, keeping its luminosity
        $this->assertEqualsWithDelta([98, 98, 98], BlendMode::Saturation->blend([51, 102, 204], [128, 128, 128]), 1);
        // The hue of a gray backdrop is none: the source takes its saturation, none, and its luminosity
        $this->assertEqualsWithDelta([128, 128, 128], BlendMode::Hue->blend([128, 128, 128], [255, 0, 0]), 1);
        // Out of the gamut, the color is clipped toward its luminosity
        $result = BlendMode::Luminosity->blend([255, 0, 0], [255, 255, 255]);
        $this->assertSame([255, 255, 255], $result);
    }

    public function testFromKeyword()
    {
        $this->assertSame(BlendMode::Multiply, BlendMode::fromKeyword(' Multiply '));
        $this->assertNull(BlendMode::fromKeyword('plus-lighter'), 'Not a <blend-mode>.');
        $this->assertSame(BlendMode::PlusLighter, BlendMode::fromKeyword('plus-lighter', plus: true));
        $this->assertNull(BlendMode::fromKeyword('nope', plus: true));
    }
}
