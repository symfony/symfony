<?php

namespace Symfony\Component\Image\Tests\Issues;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Gd\Loader;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Tests\TestCase;

class Issue17Test extends TestCase
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
        $size    = new Box(100, 10);
        $loader = $this->getLoader();

        $loader->open(FixturesLoader::getFixture('large.jpg'))
            ->thumbnail($size, ImageInterface::THUMBNAIL_OUTBOUND)
            ->save(__DIR__.'/../results/resized.jpg');

        $this->assertTrue(file_exists(__DIR__.'/../results/resized.jpg'));
        $this->assertEquals(
            $size,
            $loader->open(__DIR__.'/../results/resized.jpg')->getSize()
        );

        unlink(__DIR__.'/../results/resized.jpg');
    }
}
