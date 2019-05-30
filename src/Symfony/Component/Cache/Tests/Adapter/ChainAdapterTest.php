<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Tests\Fixtures\ExternalAdapter;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @group time-sensitive
 */
class ChainAdapterTest extends AdapterTestCase
{
    public function createCachePool($defaultLifetime = 0, $testMethod = null)
    {
        if ('testGetMetadata' === $testMethod) {
            return new ChainAdapter([new FilesystemAdapter('', $defaultLifetime)], $defaultLifetime);
        }

        return new ChainAdapter([new ArrayAdapter($defaultLifetime), new ExternalAdapter(), new FilesystemAdapter('', $defaultLifetime)], $defaultLifetime);
    }

    /**
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage At least one adapter must be specified.
     */
    public function testEmptyAdaptersException()
    {
        new ChainAdapter([]);
    }

    /**
     * @expectedException \Symfony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage The class "stdClass" does not implement
     */
    public function testInvalidAdapterException()
    {
        new ChainAdapter([new \stdClass()]);
    }

    public function testPrune()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = new ChainAdapter([
            $this->getPruneableMock(),
            $this->getNonPruneableMock(),
            $this->getPruneableMock(),
        ]);
        $this->assertTrue($cache->prune());

        $cache = new ChainAdapter([
            $this->getPruneableMock(),
            $this->getFailingPruneableMock(),
            $this->getPruneableMock(),
        ]);
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
            ->willReturn(true);

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
            ->willReturn(false);

        return $pruneable;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|AdapterInterface
     */
    private function getNonPruneableMock()
    {
        return $this
            ->getMockBuilder(AdapterInterface::class)
            ->getMock();
    }
}

interface PruneableCacheInterface extends PruneableInterface, AdapterInterface
{
}
