<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image\Palette;

use Symfony\Component\Image\Image\Palette\CMYK;
use Symfony\Component\Image\Image\Palette\Color\CMYK as CMYKColor;

class CMYKTest extends AbstractPaletteTest
{
    public function provideColorAndAlphaTuples()
    {
        $palette = $this->getPalette();

        return array(
            array(new CMYKColor($palette, array(1, 2, 3, 4)), array(1, 2, 3, 4), null),
            array(new CMYKColor($palette, array(4, 3, 2, 1)), array(4, 3, 2, 1), null),
            array(new CMYKColor($palette, array(0, 33, 67, 99)), array(3, 2, 1), null),
            array(new CMYKColor($palette, array(0, 0, 0, 0)), array(255, 255, 255), null),
            array(new CMYKColor($palette, array(0, 0, 0, 100)), array(0, 0, 0), null),
        );
    }

    public function provideColorAndAlpha()
    {
        return array(
            array(array(4, 3, 2, 1), null)
        );
    }

    public function testColorWithDifferentAlphasAreNotSame($color = null, $alpha = null)
    {
        $this->markTestSkipped('CMYK does not support alpha');
    }

    public function provideColorsForBlending()
    {
        $palette = $this->getPalette();

        return array(
            array(
                new CMYKColor($palette, array(56, 29, 38, 48)),
                new CMYKColor($palette, array(1, 2, 3, 4)),
                new CMYKColor($palette, array(50, 25, 32, 40)),
                1.1,
            ),
            array(
                new CMYKColor($palette, array(21, 12, 15, 20)),
                new CMYKColor($palette, array(1, 2, 3, 4)),
                new CMYKColor($palette, array(50, 25, 32, 40)),
                0.4,
            ),
        );
    }

    protected function getPalette()
    {
        return new CMYK();
    }
}
