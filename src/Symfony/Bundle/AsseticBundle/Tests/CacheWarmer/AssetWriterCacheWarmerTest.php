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

use Symfony\Bundle\AsseticBundle\CacheWarmer\AssetWriterCacheWarmer;
use Symfony\Component\EventDispatcher\Event;

class AssetWriterCacheWarmerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }
    }

    public function testWarmUp()
    {
        $am = $this->getMock('Assetic\\AssetManager');
        $writer = $this->getMockBuilder('Assetic\\AssetWriter')
            ->disableOriginalConstructor()
            ->getMock();
        $dispatcher = $this->getMock('Symfony\\Component\\EventDispatcher\\EventDispatcher');

        $event = new Event(null, 'assetic.write');

        $dispatcher->expects($this->once())
            ->method('notify')
            ->with($event);
        $writer->expects($this->once())
            ->method('writeManagerAssets')
            ->with($am);

        $warmer = new AssetWriterCacheWarmer($am, $writer, $dispatcher);
        $warmer->warmUp('/path/to/cache');
    }
}
