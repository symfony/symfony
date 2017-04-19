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

use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Imagick\Image;
use Symfony\Component\Image\Imagick\Layers;
use Symfony\Component\Image\Imagick\Loader;
use Symfony\Component\Image\Tests\Image\AbstractLayersTest;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Palette\RGB;

class LayersTest extends AbstractLayersTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Imagick')) {
            $this->markTestSkipped('Imagick is not installed');
        }
    }

    public function testCount()
    {
        if (!$this->supportsMockingImagick()) {
            $this->markTestSkipped('Imagick can not be mocked on this platform');
        }

        $palette = new RGB();
        $resource = $this->getMockBuilder('\Imagick')->getMock();

        $resource->expects($this->once())
            ->method('getNumberImages')
            ->will($this->returnValue(42));

        $layers = new Layers(new Image($resource, $palette, new MetadataBag()), $palette, $resource);

        $this->assertCount(42, $layers);
    }

    public function testGetLayer()
    {
        if (!$this->supportsMockingImagick()) {
            $this->markTestSkipped('Imagick can not be mocked on this platform');
        }

        $palette = new RGB();
        $resource = $this->getMockBuilder('\Imagick')->getMock();

        $resource->expects($this->any())
            ->method('getNumberImages')
            ->will($this->returnValue(2));

        $layer = $this->getMockBuilder('\Imagick')->getMock();

        $resource->expects($this->any())
            ->method('getImage')
            ->will($this->returnValue($layer));

        $layers = new Layers(new Image($resource, $palette, new MetadataBag()), $palette, $resource);

        foreach ($layers as $layer) {
            $this->assertInstanceOf(ImageInterface::class, $layer);
        }
    }

    public function testCoalesce()
    {
        $width = null;
        $height = null;

        $resource = new \Imagick();
        $palette = new RGB();
        $resource->newImage(20, 10, new \ImagickPixel('black'));
        $resource->newImage(10, 10, new \ImagickPixel('black'));

        $layers = new Layers(new Image($resource, $palette, new MetadataBag()), $palette, $resource);
        $layers->coalesce();

        foreach ($layers as $layer) {
            $size = $layer->getSize();

            if ($width === null) {
                $width = $size->getWidth();
            } else {
                $this->assertEquals($width, $size->getWidth());
            }

            if ($height === null) {
                $height = $size->getHeight();
            } else {
                $this->assertEquals($height, $size->getHeight());
            }
        }
    }

    public function getImage($path = null)
    {
        if ($path) {
            return new Image(new \Imagick($path), new RGB(), new MetadataBag());
        } else {
            return new Image(new \Imagick(), new RGB(), new MetadataBag());
        }
    }

    protected function getLoader()
    {
        return new Loader();
    }

    protected function assertLayersEquals($expected, $actual)
    {
        $this->assertEquals($expected->getImagick(), $actual->getImagick());
    }
}
