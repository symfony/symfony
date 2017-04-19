<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\Color;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Tests\TestCase;
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Image\LoaderInterface;

abstract class AbstractLoaderTest extends TestCase
{
    public function testShouldCreateEmptyImage()
    {
        $factory = $this->getLoader();
        $image = $factory->create(new Box(50, 50));
        $size = $image->getSize();

        $this->assertInstanceOf(ImageInterface::class, $image);
        $this->assertEquals(50, $size->getWidth());
        $this->assertEquals(50, $size->getHeight());
    }

    public function testShouldOpenAnImage()
    {
        $source = FixturesLoader::getFixture('google.png');
        $factory = $this->getLoader();
        $image = $factory->open($source);
        $size = $image->getSize();

        $this->assertInstanceOf(ImageInterface::class, $image);
        $this->assertEquals(364, $size->getWidth());
        $this->assertEquals(126, $size->getHeight());

        $metadata = $image->metadata();

        $this->assertEquals($source, $metadata['uri']);
        $this->assertEquals(realpath($source), $metadata['filepath']);
    }

    public function testShouldOpenAnSplFileResource()
    {
        $source = FixturesLoader::getFixture('google.png');
        $resource = new \SplFileInfo($source);
        $factory = $this->getLoader();
        $image = $factory->open($resource);
        $size = $image->getSize();

        $this->assertInstanceOf(ImageInterface::class, $image);
        $this->assertEquals(364, $size->getWidth());
        $this->assertEquals(126, $size->getHeight());

        $metadata = $image->metadata();

        $this->assertEquals($source, $metadata['uri']);
        $this->assertEquals(realpath($source), $metadata['filepath']);
    }

    public function testShouldFailOnUnknownImage()
    {
        $invalidResource = __DIR__.'/path/that/does/not/exist';

        $this->setExpectedException(InvalidArgumentException::class, sprintf('File %s does not exist.', $invalidResource));
        $this->getLoader()->open($invalidResource);
    }

    public function testShouldFailOnInvalidImage()
    {
        $source = FixturesLoader::getFixture('invalid-image.jpg');

        $this->setExpectedException(RuntimeException::class, sprintf('Unable to open image %s', $source));
        $this->getLoader()->open($source);
    }

    public function testShouldOpenAnHttpImage()
    {
        $factory = $this->getLoader();
        $image = $factory->open(self::HTTP_IMAGE);
        $size = $image->getSize();

        $this->assertInstanceOf(ImageInterface::class, $image);
        $this->assertEquals(240, $size->getWidth());
        $this->assertEquals(60, $size->getHeight());

        $metadata = $image->metadata();

        $this->assertEquals(self::HTTP_IMAGE, $metadata['uri']);
        $this->assertArrayNotHasKey('filepath', $metadata);
    }

    public function testShouldCreateImageFromString()
    {
        $factory = $this->getLoader();
        $image = $factory->load(file_get_contents(FixturesLoader::getFixture('google.png')));
        $size = $image->getSize();

        $this->assertInstanceOf(ImageInterface::class, $image);
        $this->assertEquals(364, $size->getWidth());
        $this->assertEquals(126, $size->getHeight());

        $metadata = $image->metadata();

        $this->assertArrayNotHasKey('uri', $metadata);
        $this->assertArrayNotHasKey('filepath', $metadata);
    }

    public function testShouldCreateImageFromResource()
    {
        $source = FixturesLoader::getFixture('google.png');
        $factory = $this->getLoader();
        $resource = fopen($source, 'r');
        $image = $factory->read($resource);
        $size = $image->getSize();

        $this->assertInstanceOf(ImageInterface::class, $image);
        $this->assertEquals(364, $size->getWidth());
        $this->assertEquals(126, $size->getHeight());

        $metadata = $image->metadata();

        $this->assertEquals($source, $metadata['uri']);
        $this->assertEquals(realpath($source), $metadata['filepath']);
    }

    public function testShouldCreateImageFromHttpResource()
    {
        $factory = $this->getLoader();
        $resource = fopen(self::HTTP_IMAGE, 'r');
        $image = $factory->read($resource);
        $size = $image->getSize();

        $this->assertInstanceOf(ImageInterface::class, $image);
        $this->assertEquals(240, $size->getWidth());
        $this->assertEquals(60, $size->getHeight());

        $metadata = $image->metadata();

        $this->assertEquals(self::HTTP_IMAGE, $metadata['uri']);
        $this->assertArrayNotHasKey('filepath', $metadata);
    }

    public function testShouldDetermineFontSize()
    {
        if (!$this->isFontTestSupported()) {
            $this->markTestSkipped('This install does not support font tests');
        }

        $palette = new RGB();
        $path = FixturesLoader::getFixture('font/Arial.ttf');
        $black = $palette->color('000');
        $factory = $this->getLoader();

        $this->assertEquals($this->getEstimatedFontBox(), $factory->font($path, 36, $black)->box('string'));
    }

    public function testCreateAlphaPrecision()
    {
        $loader = $this->getLoader();
        $palette = new RGB();
        $image = $loader->create(new Box(1, 1), $palette->color('#f00', 17));
        $actualColor = $image->getColorAt(new Point(0, 0));
        $this->assertEquals(17, $actualColor->getAlpha());
    }

    abstract protected function getEstimatedFontBox();

    /**
     * @return LoaderInterface
     */
    abstract protected function getLoader();

    abstract protected function isFontTestSupported();
}
