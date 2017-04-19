<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Filter;

use Symfony\Component\Image\Draw\DrawerInterface;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\LoaderInterface;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Palette\PaletteInterface;
use Symfony\Component\Image\Tests\TestCase;

abstract class FilterTestCase extends TestCase
{
    protected function getImage()
    {
        return $this->getMockBuilder(ImageInterface::class)->getMock();
    }

    protected function getLoader()
    {
        return $this->getMockBuilder(LoaderInterface::class)->getMock();
    }

    protected function getDrawer()
    {
        return $this->getMockBuilder(DrawerInterface::class)->getMock();
    }

    protected function getPalette()
    {
        return $this->getMockBuilder(PaletteInterface::class)->getMock();
    }

    protected function getColor()
    {
        return $this->getMockBuilder(ColorInterface::class)->getMock();
    }
}
