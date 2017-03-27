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

use Symfony\Component\Image\Image\Palette\Grayscale;
use Symfony\Component\Image\Image\Palette\Color\Gray;

class GrayscaleTest extends AbstractPaletteTest
{
    public function provideColorAndAlphaTuples()
    {
        $palette = $this->getPalette();

        return array(
            array(new Gray($palette, array(23), 0), array(23, 23, 23), null),
            array(new Gray($palette, array(24), 3), array(24, 24, 24), 3),
            array(new Gray($palette, array(23), 0), array(23), null),
            array(new Gray($palette, array(24), 3), array(24), 3),
            array(new Gray($palette, array(255), 0), array(255), null),
            array(new Gray($palette, array(0), 0), array(0), null),
        );
    }

    public function provideColorAndAlpha()
    {
        return array(
            array(array(23, 23, 23), 0.5),
        );
    }

    public function provideColorsForBlending()
    {
        $palette = $this->getPalette();

        return array(
            array(
                new Gray($palette, array(55), 0),
                new Gray($palette, array(1), 0),
                new Gray($palette, array(50), 0),
                1.1,
            ),
            array(
                new Gray($palette, array(21), 0),
                new Gray($palette, array(1), 0),
                new Gray($palette, array(50), 0),
                0.4,
            ),
        );
    }

    protected function getPalette()
    {
        return new Grayscale();
    }
}
