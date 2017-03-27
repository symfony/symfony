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

use Symfony\Component\Image\Image\ProfileInterface;
use Symfony\Component\Image\Tests\TestCase;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;

abstract class AbstractPaletteTest extends TestCase
{
    /**
     * @dataProvider provideColorAndAlphaTuples
     */
    public function testColor($expected, $color, $alpha)
    {
        $result = $this->getPalette()->color($color, $alpha);
        $this->assertInstanceOf(ColorInterface::class, $result);
        $this->assertEquals((string) $expected, (string) $result);
    }

    /**
     * @dataProvider provideColorAndAlpha
     */
    public function testColorIsCached($color, $alpha)
    {
        $this->assertSame($this->getPalette()->color($color, $alpha), $this->getPalette()->color($color, $alpha));
    }

    /**
     * @dataProvider provideColorAndAlpha
     */
    public function testColorWithDifferentAlphasAreNotSame($color, $alpha)
    {
        $this->assertNotSame($this->getPalette()->color($color, 2), $this->getPalette()->color($color, 0));
    }

    /**
     * @dataProvider provideColorsForBlending
     */
    public function testBlend($expected, $color1, $color2, $amount)
    {
        $result = $this->getPalette()->blend($color1, $color2, $amount);
        $this->assertInstanceOf(ColorInterface::class, $result);
        $this->assertEquals((string) $expected, (string) $result);
    }

    public function testUseProfile()
    {
        $this->getMockBuilder(ProfileInterface::class)->getMock();

        $palette = $this->getPalette();

        $new = $this->getMockBuilder(ProfileInterface::class)->getMock();
        $palette->useProfile($new);

        $this->assertEquals($new, $palette->profile());

    }

    public function testProfile()
    {
        $this->assertInstanceOf(ProfileInterface::class, $this->getPalette()->profile());
    }

    public function testName()
    {
        $this->assertInternalType('string', $this->getPalette()->name());
    }

    public function testPixelDefinition()
    {
        $this->assertInternalType('array', $this->getPalette()->pixelDefinition());

        $available = array(
            ColorInterface::COLOR_RED,
            ColorInterface::COLOR_GREEN,
            ColorInterface::COLOR_BLUE,
            ColorInterface::COLOR_CYAN,
            ColorInterface::COLOR_MAGENTA,
            ColorInterface::COLOR_YELLOW,
            ColorInterface::COLOR_KEYLINE,
            ColorInterface::COLOR_GRAY,
        );

        foreach ($this->getPalette()->pixelDefinition() as $color) {
            $this->assertTrue(in_array($color, $available));
        }
    }

    public function testSupportsAlpha()
    {
        $this->assertInternalType('boolean', $this->getPalette()->supportsAlpha());
    }

    abstract public function provideColorAndAlphaTuples();

    abstract public function provideColorsForBlending();

    /**
     * @return PaletteInterface
     */
    abstract protected function getPalette();
}
