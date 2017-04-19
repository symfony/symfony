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

use Symfony\Component\Image\Gmagick\Layers;
use Symfony\Component\Image\Gmagick\Image;
use Symfony\Component\Image\Gmagick\Loader;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Tests\Image\AbstractLayersTest;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Palette\RGB;

class LayersTest extends AbstractLayersTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Gmagick')) {
            $this->markTestSkipped('Gmagick is not installed');
        }
    }

    public function testCount()
    {
        $palette = new RGB();
        $resource = $this->getMockBuilder('\Gmagick')->getMock();

        $resource->expects($this->once())
            ->method('getnumberimages')
            ->will($this->returnValue(42));

        $layers = new Layers(new Image($resource, $palette, new MetadataBag()), $palette, $resource);

        $this->assertCount(42, $layers);
    }

    public function testGetLayer()
    {
        $palette = new RGB();
        $resource = $this->getMockBuilder('\Gmagick')->getMock();

        $resource->expects($this->any())
            ->method('getnumberimages')
            ->will($this->returnValue(2));

        $layer = $this->getMockBuilder('\Gmagick')->getMock();

        $resource->expects($this->any())
            ->method('getimage')
            ->will($this->returnValue($layer));

        $layers = new Layers(new Image($resource, $palette, new MetadataBag()), $palette, $resource);

        foreach ($layers as $layer) {
            $this->assertInstanceOf(ImageInterface::class, $layer);
        }
    }

    public function testAnimateEmpty()
    {
        $this->markTestSkipped('Animate empty is skipped due to https://bugs.php.net/bug.php?id=62309');
    }

    public function getImage($path = null)
    {
        if ($path) {
            return new Image(new \Gmagick($path), new RGB(), new MetadataBag());
        } else {
            return new Image(new \Gmagick(), new RGB(), new MetadataBag());
        }
    }

    public function getLoader()
    {
        return new Loader();
    }

    public function getLayers(ImageInterface $image, $resource)
    {
        return new Layers($image, $resource, new MetadataBag());
    }

    protected function assertLayersEquals($expected, $actual)
    {
        $this->assertEquals($expected->getGmagick(), $actual->getGmagick());
    }
}
