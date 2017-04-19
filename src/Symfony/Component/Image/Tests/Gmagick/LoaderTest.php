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

use Symfony\Component\Image\Gmagick\Loader;
use Symfony\Component\Image\Tests\Image\AbstractLoaderTest;
use Symfony\Component\Image\Image\Box;

class LoaderTest extends AbstractLoaderTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Gmagick')) {
            $this->markTestSkipped('Gmagick is not installed');
        }
    }

    public function testCreateAlphaPrecision()
    {
        $this->markTestSkipped('Alpha transparency is not supported by Gmagick');
    }

    protected function getEstimatedFontBox()
    {
        return new Box(117, 55);
    }

    protected function getLoader()
    {
        return new Loader();
    }

    protected function isFontTestSupported()
    {
        return true;
    }
}
