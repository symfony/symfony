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

use Psr\Cache\CacheItemPoolInterface;
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
    public function createCachePool(int $defaultLifetime = 0, string $testMethod = null): CacheItemPoolInterface
    {
        if ('testGetMetadata' === $testMethod) {
            return new ChainAdapter([new FilesystemAdapter('', $defaultLifetime)], $defaultLifetime);
        }

        return new ChainAdapter([new ArrayAdapter($defaultLifetime), new ExternalAdapter(), new FilesystemAdapter('', $defaultLifetime)], $defaultLifetime);
    }

    public function testEmptyAdaptersException()
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('At least one adapter must be specified.');
        new ChainAdapter([]);
    }

    public function testInvalidAdapterException()
    {
        $this->expectException('Symfony\Component\Cache\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The class "stdClass" does not implement');
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

    private function getPruneableMock(): AdapterInterface
    {
        $pruneable = $this->createMock([PruneableInterface::class, AdapterInterface::class]);

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(true);

        return $pruneable;
    }

    private function getFailingPruneableMock(): AdapterInterface
    {
        $pruneable = $this->createMock([PruneableInterface::class, AdapterInterface::class]);

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(false);

        return $pruneable;
    }

    private function getNonPruneableMock(): AdapterInterface
    {
        return $this->createMock(AdapterInterface::class);
    }
}
