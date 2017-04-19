<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Gd;

use Symfony\Component\Image\Gd\Loader;
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Tests\Image\AbstractImageTest;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Exception\RuntimeException;

class ImageTest extends AbstractImageTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!function_exists('gd_info')) {
            $this->markTestSkipped('Gd not installed');
        }
    }

    public function testImageResolutionChange()
    {
        $this->markTestSkipped('GD driver does not support resolution options');
    }

    public function provideFilters()
    {
        return array(
            array(ImageInterface::FILTER_UNDEFINED),
        );
    }

    public function providePalettes()
    {
        return array(
            array(RGB::class, array(255, 0, 0)),
        );
    }

    public function provideFromAndToPalettes()
    {
        return array(
            array(
                RGB::class,
                RGB::class,
                array(10, 10, 10),
            ),
        );
    }

    public function testProfile()
    {
        try {
            parent::testProfile();
            $this->fail('A RuntimeException should have been raised');
        } catch (RuntimeException $e) {
            $this->assertSame('GD driver does not support color profiles', $e->getMessage());
        }
    }

    public function testPaletteIsGrayIfGrayImage()
    {
        $this->markTestSkipped('Gd does not support Gray colorspace');
    }

    public function testPaletteIsCMYKIfCMYKImage()
    {
        $this->markTestSkipped('GD driver does not recognize CMYK images properly');
    }

    public function testGetColorAtCMYK()
    {
        $this->markTestSkipped('GD driver does not recognize CMYK images properly');
    }

    public function testChangeColorSpaceAndStripImage()
    {
        $this->markTestSkipped('GD driver does not support ICC profiles');
    }

    public function testStripImageWithInvalidProfile()
    {
        $this->markTestSkipped('GD driver does not support ICC profiles');
    }

    public function testStripGBRImageHasGoodColors()
    {
        $this->markTestSkipped('GD driver does not support ICC profiles');
    }

    protected function getLoader()
    {
        return new Loader();
    }

    protected function supportMultipleLayers()
    {
        return false;
    }

    public function testRotateWithNoBackgroundColor()
    {
        if (version_compare(PHP_VERSION, '5.5', '>=')) {
            // see https://bugs.php.net/bug.php?id=65148
            $this->markTestSkipped('Disabling test while bug #65148 is open');
        }

        parent::testRotateWithNoBackgroundColor();
    }

    /**
     * @dataProvider provideVariousSources
     */
    public function testResolutionOnSave($source)
    {
        $this->markTestSkipped('Gd only supports 72 dpi resolution');
    }

    protected function getImageResolution(ImageInterface $image)
    {
    }
}
