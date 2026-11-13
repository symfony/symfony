<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Style;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\TuiHtml\Style\Filter;

final class FilterTest extends TestCase
{
    #[DataProvider('provideFilters')]
    public function testApply(array $functions, array $expected)
    {
        $this->assertSame($expected, (new Filter($functions))->apply([255, 0, 0], [0, 0, 255]));
    }

    public static function provideFilters(): iterable
    {
        // The values browsers draw for red, from the matrices of the Filter Effects spec
        yield 'grayscale' => [[['grayscale', 1.0]], [54, 54, 54]];
        yield 'grayscale half' => [[['grayscale', 0.5]], [155, 27, 27]];
        yield 'grayscale over 1 is 1' => [[['grayscale', 3.0]], [54, 54, 54]];
        yield 'sepia' => [[['sepia', 1.0]], [100, 89, 69]];
        yield 'saturate 0' => [[['saturate', 0.0]], [54, 54, 54]];
        yield 'saturate over 1, clamped' => [[['saturate', 2.0]], [255, 0, 0]];
        yield 'hue-rotate' => [[['hue-rotate', 180.0]], [0, 109, 109]];
        yield 'hue-rotate a turn' => [[['hue-rotate', 360.0]], [255, 0, 0]];
        yield 'brightness' => [[['brightness', 0.5]], [128, 0, 0]];
        yield 'contrast' => [[['contrast', 0.5]], [191, 64, 64]];
        yield 'invert' => [[['invert', 1.0]], [0, 255, 255]];
        yield 'invert half' => [[['invert', 0.5]], [128, 128, 128]];
        yield 'opacity, over what is behind' => [[['opacity', 0.5]], [128, 0, 128]];
        yield 'in order' => [[['invert', 1.0], ['grayscale', 1.0]], [201, 201, 201]];
    }

    public function testUnknownFunction()
    {
        $this->expectException(InvalidArgumentException::class);
        new Filter([['blur', 2.0]]);
    }

    public function testNegativeAmount()
    {
        $this->expectException(InvalidArgumentException::class);
        new Filter([['sepia', -1.0]]);
    }
}
