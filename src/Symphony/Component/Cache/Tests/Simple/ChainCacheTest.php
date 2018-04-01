<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Simple;

use Psr\SimpleCache\CacheInterface;
use Symphony\Component\Cache\PruneableInterface;
use Symphony\Component\Cache\Simple\ArrayCache;
use Symphony\Component\Cache\Simple\ChainCache;
use Symphony\Component\Cache\Simple\FilesystemCache;

/**
 * @group time-sensitive
 */
class ChainCacheTest extends CacheTestCase
{
    public function createSimpleCache($defaultLifetime = 0)
    {
        return new ChainCache(array(new ArrayCache($defaultLifetime), new FilesystemCache('', $defaultLifetime)), $defaultLifetime);
    }

    /**
     * @expectedException \Symphony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage At least one cache must be specified.
     */
    public function testEmptyCachesException()
    {
        new ChainCache(array());
    }

    /**
     * @expectedException \Symphony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage The class "stdClass" does not implement
     */
    public function testInvalidCacheException()
    {
        new ChainCache(array(new \stdClass()));
    }

    public function testPrune()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = new ChainCache(array(
            $this->getPruneableMock(),
            $this->getNonPruneableMock(),
            $this->getPruneableMock(),
        ));
        $this->assertTrue($cache->prune());

        $cache = new ChainCache(array(
            $this->getPruneableMock(),
            $this->getFailingPruneableMock(),
            $this->getPruneableMock(),
        ));
        $this->assertFalse($cache->prune());
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|PruneableCacheInterface
     */
    private function getPruneableMock()
    {
        $pruneable = $this
            ->getMockBuilder(PruneableCacheInterface::class)
            ->getMock();

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->will($this->returnValue(true));

        return $pruneable;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|PruneableCacheInterface
     */
    private function getFailingPruneableMock()
    {
        $pruneable = $this
            ->getMockBuilder(PruneableCacheInterface::class)
            ->getMock();

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->will($this->returnValue(false));

        return $pruneable;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|CacheInterface
     */
    private function getNonPruneableMock()
    {
        return $this
            ->getMockBuilder(CacheInterface::class)
            ->getMock();
    }
}

interface PruneableCacheInterface extends PruneableInterface, CacheInterface
{
}
