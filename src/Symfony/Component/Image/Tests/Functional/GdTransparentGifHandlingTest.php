<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Functional;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Gd\Loader;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Tests\TestCase;

class GdTransparentGifHandlingTest extends TestCase
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
        $loader = $this->getLoader();
        $new = sys_get_temp_dir().'/sample.jpeg';

        $image = $loader->open(FixturesLoader::getFixture('xparent.gif'));
        $size = $image->getSize()->scale(0.5);

        $image
            ->resize($size)
        ;

        $image = $loader
            ->create($size)
            ->paste($image, new Point(0, 0))
            ->save($new)
        ;

        $this->assertSame(272, $image->getSize()->getWidth());
        $this->assertSame(171, $image->getSize()->getHeight());
    }
}
