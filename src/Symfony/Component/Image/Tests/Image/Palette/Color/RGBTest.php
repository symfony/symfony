<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image\Palette\Color;

use Symfony\Component\Image\Image\Palette\Color\RGB;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Palette\RGB as RGBPalette;

class RGBTest extends AbstractColorTest
{
    public function provideOpaqueColors()
    {
        return array(
            array(new RGB(new RGBPalette(), array(12, 123, 245), 100)),
            array(new RGB(new RGBPalette(), array(0, 0, 0), 100)),
            array(new RGB(new RGBPalette(), array(255, 255, 255), 100)),
        );
    }
    public function provideNotOpaqueColors()
    {
        return array(
            array($this->getColor()),
            array(new RGB(new RGBPalette(), array(12, 123, 245), 23)),
            array(new RGB(new RGBPalette(), array(0, 0, 0), 45)),
            array(new RGB(new RGBPalette(), array(255, 255, 255), 0)),
        );
    }

    public function provideGrayscaleData()
    {
        return array(
            array('#686868', $this->getColor()),
        );
    }

    public function provideColorAndAlphaTuples()
    {
        return array(
            array(14, $this->getColor())
        );
    }

    protected function getColor()
    {
        return new RGB(new RGBPalette(), array(12, 123, 245), 14);
    }

    public function provideColorAndValueComponents()
    {
        return array(
            array(array(
                ColorInterface::COLOR_RED => 12,
                ColorInterface::COLOR_GREEN => 123,
                ColorInterface::COLOR_BLUE => 245,
            ), $this->getColor()),
        );
    }
}
