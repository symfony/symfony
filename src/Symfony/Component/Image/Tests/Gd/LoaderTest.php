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

use Symfony\Component\Image\Gd\Loader;
use Symfony\Component\Image\Tests\Image\AbstractLoaderTest;
use Symfony\Component\Image\Image\Box;

class LoaderTest extends AbstractLoaderTest
{
    protected function setUp()
    {
        parent::setUp();

        if (!function_exists('gd_info')) {
            $this->markTestSkipped('Gd not installed');
        }
    }

    protected function getEstimatedFontBox()
    {
        if (defined('HHVM_VERSION_ID')) {
            return new Box(112, 46);
        }

        if (PHP_VERSION_ID >= 70000) {
            return new Box(112, 45);
        }

        return new Box(112, 46);
    }

    protected function getLoader()
    {
        return new Loader();
    }

    protected function isFontTestSupported()
    {
        $infos = gd_info();

        return isset($infos['FreeType Support']) ? $infos['FreeType Support'] : false;
    }
}
