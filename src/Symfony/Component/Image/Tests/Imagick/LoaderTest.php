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

use Symfony\Component\Image\Imagick\Loader;
use Symfony\Component\Image\Tests\Image\AbstractLoaderTest;
use Symfony\Component\Image\Image\Box;

class LoaderTest extends AbstractLoaderTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Imagick')) {
            $this->markTestSkipped('Imagick is not installed');
        }
    }

    public function testShouldOpenAnHttpImage()
    {
        if (defined('HHVM_VERSION_ID')) {
            $this->markTestSkipped('Imagick on HHVM does not support opening URLs');
        }

        return parent::testShouldOpenAnHttpImage();
    }

    protected function getEstimatedFontBox()
    {
        if (defined('HHVM_VERSION_ID')) {
            return new Box(116, 55);
        }

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
