<?php

namespace Symfony\Component\Image\Tests\Regression;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Gd\Loader;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Tests\TestCase;

class RegressionGIFTest extends TestCase
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

    public function testShouldSaveGifImageWithMoreThan256TransparentPixels()
    {
        $loader = $this->getLoader();
        $new = sys_get_temp_dir().'/sample.jpeg';

        $image = $loader
            ->open(FixturesLoader::getFixture('sample.gif'))
            ->save($new)
        ;

        $this->assertSame(700, $image->getSize()->getWidth());
        $this->assertSame(440, $image->getSize()->getHeight());
    }
}
