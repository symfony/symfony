<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Adapter;

use Symphony\Component\Cache\Adapter\AdapterInterface;
use Symphony\Component\Cache\Adapter\FilesystemAdapter;
use Symphony\Component\Cache\Adapter\ArrayAdapter;
use Symphony\Component\Cache\Adapter\ChainAdapter;
use Symphony\Component\Cache\PruneableInterface;
use Symphony\Component\Cache\Tests\Fixtures\ExternalAdapter;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @group time-sensitive
 */
class ChainAdapterTest extends AdapterTestCase
{
    public function createCachePool($defaultLifetime = 0)
    {
        return new ChainAdapter(array(new ArrayAdapter($defaultLifetime), new ExternalAdapter(), new FilesystemAdapter('', $defaultLifetime)), $defaultLifetime);
    }

    /**
     * @expectedException \Symphony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage At least one adapter must be specified.
     */
    public function testEmptyAdaptersException()
    {
        new ChainAdapter(array());
    }

    /**
     * @expectedException \Symphony\Component\Cache\Exception\InvalidArgumentException
     * @expectedExceptionMessage The class "stdClass" does not implement
     */
    public function testInvalidAdapterException()
    {
        new ChainAdapter(array(new \stdClass()));
    }

    public function testPrune()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = new ChainAdapter(array(
            $this->getPruneableMock(),
            $this->getNonPruneableMock(),
            $this->getPruneableMock(),
        ));
        $this->assertTrue($cache->prune());

        $cache = new ChainAdapter(array(
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
