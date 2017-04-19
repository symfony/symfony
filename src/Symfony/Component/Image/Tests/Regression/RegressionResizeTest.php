<?php

namespace Symfony\Component\Image\Tests\Regression;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Gd\Loader;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Tests\TestCase;

class RegressionResizeTest extends TestCase
{
    private function getLoader()
    {
        try {
            $loader = new Loader();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        return $loader;
    }

    public function testShouldResize()
    {
        $size = new Box(100, 10);
        $loader = $this->getLoader();

        $loader->open(FixturesLoader::getFixture('large.jpg'))
            ->thumbnail($size, ImageInterface::THUMBNAIL_OUTBOUND)
            ->save($this->getTempDir().'/resized.jpg');

        $this->assertFileExists($this->getTempDir().'/resized.jpg');
        $this->assertEquals(
            $size,
            $loader->open($this->getTempDir().'/resized.jpg')->getSize()
        );
    }
}
