<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Config;

use Symfony\Component\Config\GlobLocator;

class GlobLocatorTest extends \PHPUnit_Framework_TestCase
{
    public function testLocateFindsOnlyDirs()
    {
        $loader = new GlobLocator(__DIR__.'/Fixtures');

        $this->assertEquals(
            array(__DIR__.DIRECTORY_SEPARATOR.'Fixtures/Again/', __DIR__.DIRECTORY_SEPARATOR.'Fixtures/Builder/'),
            $loader->locate('*/'),
            '->locate() returns the absolute dirname if the glob pattern matches directory the given path'
        );

        $this->assertEquals(
            array(),
            $loader->locate('*.php', __DIR__),
            '->locate() returns an empty array if only files are matched'
        );
    }
}
