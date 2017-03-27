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
use Symfony\Component\Image\Tests\Draw\AbstractDrawerTest;

class DrawerTest extends AbstractDrawerTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Imagick')) {
            $this->markTestSkipped('Imagick is not installed');
        }
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
