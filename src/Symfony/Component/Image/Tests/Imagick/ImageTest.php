<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Imagick;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Imagick\Loader;
use Symfony\Component\Image\Imagick\Image;
use Symfony\Component\Image\Image\Palette\CMYK;
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Tests\Image\AbstractImageTest;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Imagick\Image as ImagickImage;

class ImageTest extends AbstractImageTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Imagick')) {
            $this->markTestSkipped('Imagick is not installed');
        }
    }

    protected function tearDown()
    {
        if (class_exists('Imagick')) {
            $prop = new \ReflectionProperty(ImagickImage::class, 'supportsColorspaceConversion');
            $prop->setAccessible(true);
            $prop->setValue(null);
        }

        parent::tearDown();
    }

    protected function getLoader()
    {
        return new Loader();
    }

    public function testImageResizeUsesProperMethodBasedOnInputAndOutputSizes()
    {
        $loader = $this->getLoader();

        $image = $loader->open(FixturesLoader::getFixture('resize/210-design-19933.jpg'));

        $image
            ->resize(new Box(1500, 750))
            ->save($this->getTempDir().'/large.png')
        ;

        $this->assertSame(1500, $image->getSize()->getWidth());
        $this->assertSame(750, $image->getSize()->getHeight());

        $image
            ->resize(new Box(100, 50))
            ->save($this->getTempDir().'/small.png')
        ;

        $this->assertSame(100, $image->getSize()->getWidth());
        $this->assertSame(50, $image->getSize()->getHeight());
    }

    public function testAnimatedGifResize()
    {
        $loader = $this->getLoader();
        $image = $loader->open(FixturesLoader::getFixture('anima3.gif'));
        $image
            ->resize(new Box(150, 100))
            ->save($this->getTempDir().'/anima3-150x100-actual.gif', array('animated' => true))
        ;
        $this->assertImageEquals(
            $loader->open(FixturesLoader::getFixture('resize/anima3-150x100.gif')),
            $loader->open($this->getTempDir().'/anima3-150x100-actual.gif')
        );
    }

    // Older imagemagick versions does not support colorspace conversion
    public function testOlderImageMagickDoesNotAffectColorspaceUsageOnConstruct()
    {
        if (!$this->supportsMockingImagick()) {
            $this->markTestSkipped('Imagick can not be mocked on this platform');
        }

        $palette = new CMYK();
        $imagick = $this->getMockBuilder('\Imagick')->disableOriginalConstructor()->getMock();
        $imagick->expects($this->any())
            ->method('setColorspace')
            ->will($this->throwException(new \RuntimeException('Method not supported')));

        $prop = new \ReflectionProperty(ImagickImage::class, 'supportsColorspaceConversion');
        $prop->setAccessible(true);
        $prop->setValue(false);

        // Avoid test marked as risky
        $this->assertTrue(true);

        return new Image($imagick, $palette, new MetadataBag());
    }

    /**
     * @depends testOlderImageMagickDoesNotAffectColorspaceUsageOnConstruct
     * @expectedException \Symfony\Component\Image\Exception\RuntimeException
     * @expectedExceptionMessage Your version of Imagick does not support colorspace conversions.
     */
    public function testOlderImageMagickDoesNotAffectColorspaceUsageOnPaletteChange($image)
    {
        $image->usePalette(new RGB());
    }

    public function testAnimatedGifCrop()
    {
        $loader = $this->getLoader();
        $image = $loader->open(FixturesLoader::getFixture('anima3.gif'));
        $image
            ->crop(
                new Point(0, 0),
                new Box(150, 100)
            )
            ->save($this->getTempDir().'/anima3-topleft-actual.gif', array('animated' => true))
        ;
        $this->assertImageEquals(
            $loader->open(FixturesLoader::getFixture('crop/anima3-topleft.gif')),
            $loader->open($this->getTempDir().'/anima3-topleft-actual.gif')
        );
    }

    protected function supportMultipleLayers()
    {
        return true;
    }

    protected function getImageResolution(ImageInterface $image)
    {
        return $image->getImagick()->getImageResolution();
    }
}
