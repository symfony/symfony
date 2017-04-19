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

use Symfony\Component\Image\Image\Palette\PaletteInterface;
use Symfony\Component\Image\Tests\TestCase;

abstract class AbstractColorTest extends TestCase
{
    /**
     * @dataProvider provideColorAndAlphaTuples
     */
    public function testGetAlpha($expected, $color)
    {
        $this->assertEquals($expected, $color->getAlpha());
    }

    public function testGetPalette()
    {
        $this->assertInstanceOf(PaletteInterface::class, $this->getColor()->getPalette());
    }

    /**
     * @dataProvider provideColorAndValueComponents
     */
    public function testGetvalue($expected, $color)
    {
        $data = array();

        foreach ($color->getPalette()->pixelDefinition() as $component) {
            $data[$component] = $color->getValue($component);
        }

        $this->assertEquals($expected, $data);
    }

    public function testDissolve()
    {
        $color = $this->getColor();
        $alpha = $color->getAlpha();
        $signature = (string) $color;

        $color = $color->dissolve(2);

        $this->assertEquals(2 + $alpha, $color->getAlpha());
        $this->assertEquals($signature, (string) $color);
    }

    public function testLighten()
    {
        $color = $this->getColor();

        $data = array();

        foreach ($color->getPalette()->pixelDefinition() as $component) {
            $data[$component] = $color->getValue($component);
        }

        $color->lighten(4);

        foreach ($color->getPalette()->pixelDefinition() as $component) {
            $this->assertLessThanOrEqual($data[$component], $color->getValue($component));
        }
    }

    public function testDarken()
    {
        $color = $this->getColor();

        $data = array();

        foreach ($color->getPalette()->pixelDefinition() as $component) {
            $data[$component] = $color->getValue($component);
        }

        $color->darken(4);

        foreach ($color->getPalette()->pixelDefinition() as $component) {
            $this->assertGreaterThanOrEqual($data[$component], $color->getValue($component));
        }
    }

    /**
     * @dataProvider provideGrayscaleData
     */
    public function testGrayscale($expected, $color)
    {
        $this->assertEquals($expected, (string) $color->grayscale());
    }

    /**
     * @dataProvider provideOpaqueColors
     */
    public function testIsOpaque($color)
    {
        $this->assertTrue($color->isOpaque());
    }

    /**
     * @dataProvider provideNotOpaqueColors
     */
    public function testIsNotOpaque($color)
    {
        $this->assertFalse($color->isOpaque());
    }

    abstract public function provideColorAndValueComponents();

    abstract public function provideOpaqueColors();

    abstract public function provideNotOpaqueColors();

    abstract public function provideGrayscaleData();

    abstract public function provideColorAndAlphaTuples();

    /**
     * @return ColorInterface
     */
    abstract protected function getColor();
}
