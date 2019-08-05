<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Simple;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Simple\ArrayCache;
use Symfony\Component\Cache\Simple\ChainCache;
use Symfony\Component\Cache\Simple\FilesystemCache;

/**
 * @group time-sensitive
 */
class ChainCacheTest extends CacheTestCase
{
    public function createSimpleCache($defaultLifetime = 0)
    {
        return new ChainCache([new ArrayCache($defaultLifetime), new FilesystemCache('', $defaultLifetime)], $defaultLifetime);
    }

    public function testEmptyCachesException()
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('At least one cache must be specified.');
        new ChainCache([]);
    }

    public function testInvalidCacheException()
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The class "stdClass" does not implement');
        new ChainCache([new \stdClass()]);
    }

    public function testPrune()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = new ChainCache([
            $this->getPruneableMock(),
            $this->getNonPruneableMock(),
            $this->getPruneableMock(),
        ]);
        $this->assertTrue($cache->prune());

        $cache = new ChainCache([
            $this->getPruneableMock(),
            $this->getFailingPruneableMock(),
            $this->getPruneableMock(),
        ]);
        $this->assertFalse($cache->prune());
    }

    /**
     * @return MockObject|PruneableCacheInterface
     */
    private function getPruneableMock()
    {
        $pruneable = $this
            ->getMockBuilder(PruneableCacheInterface::class)
            ->getMock();

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(true);

        return $pruneable;
    }

    /**
     * @return MockObject|PruneableCacheInterface
     */
    private function getFailingPruneableMock()
    {
        $pruneable = $this
            ->getMockBuilder(PruneableCacheInterface::class)
            ->getMock();

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(false);

        return $pruneable;
    }

    /**
     * @return MockObject|CacheInterface
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
