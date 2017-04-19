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

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Gd\Layers;
use Symfony\Component\Image\Gd\Image;
use Symfony\Component\Image\Gd\Loader;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Image\Palette\PaletteInterface;
use Symfony\Component\Image\Tests\Image\AbstractLayersTest;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Palette\RGB;

class LayersTest extends AbstractLayersTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!function_exists('gd_info')) {
            $this->markTestSkipped('Gd not installed');
        }
    }

    public function testCount()
    {
        $resource = imagecreate(20, 20);
        $palette = $this->getMockBuilder(PaletteInterface::class)->getMock();
        $layers = new Layers(new Image($resource, $palette, new MetadataBag()), $palette, $resource);

        $this->assertCount(1, $layers);
    }

    public function testGetLayer()
    {
        $resource = imagecreate(20, 20);
        $palette = $this->getMockBuilder(PaletteInterface::class)->getMock();
        $layers = new Layers(new Image($resource, $palette, new MetadataBag()), $palette, $resource);

        foreach ($layers as $layer) {
            $this->assertInstanceOf(ImageInterface::class, $layer);
        }
    }

    public function testLayerArrayAccess()
    {
        $image = $this->getImage(FixturesLoader::getFixture('pink.gif'));
        $layers = $image->layers();

        $this->assertLayersEquals($image, $layers[0]);
        $this->assertTrue(isset($layers[0]));
    }

    public function testLayerAddGetSetRemove()
    {
        $image = $this->getImage(FixturesLoader::getFixture('pink.gif'));
        $layers = $image->layers();

        $this->assertLayersEquals($image, $layers->get(0));
        $this->assertTrue($layers->has(0));
    }

    public function testLayerArrayAccessInvalidArgumentExceptions($offset = null)
    {
        $this->markTestSkipped('Gd does not fully support layers array access');
    }

    public function testLayerArrayAccessOutOfBoundsExceptions($offset = null)
    {
        $this->markTestSkipped('Gd does not fully support layers array access');
    }

    public function testAnimateEmpty()
    {
        $this->markTestSkipped('Gd does not support animated gifs');
    }

    public function testAnimateLoaded()
    {
        $this->markTestSkipped('Gd does not support animated gifs');
    }

    /**
     * @dataProvider provideAnimationParameters
     */
    public function testAnimateWithParameters($delay, $loops)
    {
        $this->markTestSkipped('Gd does not support animated gifs');
    }

    /**
     * @dataProvider provideAnimationParameters
     */
    public function testAnimateWithWrongParameters($delay, $loops)
    {
        $this->markTestSkipped('Gd does not support animated gifs');
    }

    public function getImage($path = null)
    {
        return new Image(imagecreatetruecolor(10, 10), new RGB(), new MetadataBag());
    }

    public function getLayers(ImageInterface $image, $resource)
    {
        return new Layers($image, new RGB(), $resource);
    }

    public function getLoader()
    {
        return new Loader();
    }

    protected function assertLayersEquals($expected, $actual)
    {
        $this->assertEquals($expected->getGdResource(), $actual->getGdResource());
    }
}
