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

use Symfony\Component\Image\Image\Palette\Color\Gray;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Palette\Grayscale;

class GrayTest extends AbstractColorTest
{
    public function provideOpaqueColors()
    {
        return array(
            array(new Gray(new Grayscale(), array(12), 100)),
            array(new Gray(new Grayscale(), array(0), 100)),
            array(new Gray(new Grayscale(), array(255), 100)),
        );
    }
    public function provideNotOpaqueColors()
    {
        return array(
            array($this->getColor()),
            array(new Gray(new Grayscale(), array(12), 23)),
            array(new Gray(new Grayscale(), array(0), 45)),
            array(new Gray(new Grayscale(), array(255), 0)),
        );
    }

    public function provideGrayscaleData()
    {
        return array(
            array('#0c0c0c', $this->getColor()),
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
        return new Gray(new Grayscale(), array(12), 14);
    }

    public function provideColorAndValueComponents()
    {
        return array(
            array(array(
                ColorInterface::COLOR_GRAY => 12,
            ), $this->getColor()),
        );
    }
}
