<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\CacheWarmer;

use Symfony\Bundle\AsseticBundle\CacheWarmer\AssetManagerCacheWarmer;

class AssetManagerCacheWarmerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }
    }

    public function testWarmUp()
    {
        $am = $this
            ->getMockBuilder('Assetic\\Factory\\LazyAssetManager')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $am->expects($this->once())->method('load');

        $container = $this
            ->getMockBuilder('Symfony\\Component\\DependencyInjection\\Container')
            ->setConstructorArgs(array())
            ->getMock()
        ;

        $container
            ->expects($this->once())
            ->method('get')
            ->with('assetic.asset_manager')
            ->will($this->returnValue($am))
        ;

        $warmer = new AssetManagerCacheWarmer($container);
        $warmer->warmUp('/path/to/cache');
    }
}
