<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Gmagick;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Gmagick\Loader;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Palette\CMYK;
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Tests\Image\AbstractImageTest;

class ImageTest extends AbstractImageTest
{
    protected function setUp()
    {
        parent::setUp();

        // disable GC while https://bugs.php.net/bug.php?id=63677 is still open
        // If GC enabled, Gmagick unit tests fail
        gc_disable();

        if (!class_exists('Gmagick')) {
            $this->markTestSkipped('Gmagick is not installed');
        }
    }

    // We redeclare this test because Gmagick does not support alpha
    public function testGetColorAt()
    {
        $color = $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('65-percent-black.png'))
            ->getColorAt(new Point(0, 0));

        $this->assertEquals('#000000', (string) $color);
        // Gmagick does not supports alpha
        $this->assertTrue($color->isOpaque());
    }

    public function provideFromAndToPalettes()
    {
        return array(
            array(
                RGB::class,
                CMYK::class,
                array(10, 10, 10),
            ),
            array(
                CMYK::class,
                RGB::class,
                array(10, 10, 10, 0),
            ),
        );
    }

    public function providePalettes()
    {
        return array(
            array(RGB::class, array(255, 0, 0)),
            array(CMYK::class, array(10, 0, 0, 0)),
        );
    }

    public function testPaletteIsGrayIfGrayImage()
    {
        $this->markTestSkipped('Gmagick does not support Gray colorspace, because of the lack omg image type support');
    }

    public function testGetColorAtCMYK()
    {
        $this->markTestSkipped('Gmagick fails to read CMYK colors properly, see https://bugs.php.net/bug.php?id=67435');
    }

    public function testImageCreatedAlpha()
    {
        $this->markTestSkipped('Alpha transparency is not supported by Gmagick');
    }

    public function testFillAlphaPrecision()
    {
        $this->markTestSkipped('Alpha transparency is not supported by Gmagick');
    }

    protected function getLoader()
    {
        return new Loader();
    }

    protected function supportMultipleLayers()
    {
        return true;
    }

    protected function getImageResolution(ImageInterface $image)
    {
        return $image->getGmagick()->getimageresolution();
    }
}
