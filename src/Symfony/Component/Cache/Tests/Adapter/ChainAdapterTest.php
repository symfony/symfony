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
use Symfony\Component\Cache\Tests\Fixtures\ExternalAdapter;
use Symfony\Component\Cache\Tests\Fixtures\PrunableAdapter;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @group time-sensitive
 */
class ChainAdapterTest extends AdapterTestCase
{
    public function createCachePool(int $defaultLifetime = 0, string $testMethod = null): CacheItemPoolInterface
    {
        if ('testGetMetadata' === $testMethod) {
            return new ChainAdapter([new FilesystemAdapter('a', $defaultLifetime), new FilesystemAdapter('b', $defaultLifetime)], $defaultLifetime);
        }

        return new ChainAdapter([new ArrayAdapter($defaultLifetime), new ExternalAdapter($defaultLifetime), new FilesystemAdapter('', $defaultLifetime)], $defaultLifetime);
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

    public function testMultipleCachesExpirationWhenCommonTtlIsNotSet()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $adapter1 = new ArrayAdapter(4);
        $adapter2 = new ArrayAdapter(2);

        $cache = new ChainAdapter([$adapter1, $adapter2]);

        $cache->save($cache->getItem('key')->set('value'));

        $item = $adapter1->getItem('key');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value', $item->get());

        $item = $adapter2->getItem('key');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value', $item->get());

        sleep(2);

        $item = $adapter1->getItem('key');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value', $item->get());

        $item = $adapter2->getItem('key');
        $this->assertFalse($item->isHit());

        sleep(2);

        $item = $adapter1->getItem('key');
        $this->assertFalse($item->isHit());

        $adapter2->save($adapter2->getItem('key1')->set('value1'));

        $item = $cache->getItem('key1');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value1', $item->get());

        sleep(2);

        $item = $adapter1->getItem('key1');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value1', $item->get());

        $item = $adapter2->getItem('key1');
        $this->assertFalse($item->isHit());

        sleep(2);

        $item = $adapter1->getItem('key1');
        $this->assertFalse($item->isHit());
    }

    public function testMultipleCachesExpirationWhenCommonTtlIsSet()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $adapter1 = new ArrayAdapter(4);
        $adapter2 = new ArrayAdapter(2);

        $cache = new ChainAdapter([$adapter1, $adapter2], 6);

        $cache->save($cache->getItem('key')->set('value'));

        $item = $adapter1->getItem('key');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value', $item->get());

        $item = $adapter2->getItem('key');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value', $item->get());

        sleep(2);

        $item = $adapter1->getItem('key');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value', $item->get());

        $item = $adapter2->getItem('key');
        $this->assertFalse($item->isHit());

        sleep(2);

        $item = $adapter1->getItem('key');
        $this->assertFalse($item->isHit());

        $adapter2->save($adapter2->getItem('key1')->set('value1'));

        $item = $cache->getItem('key1');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value1', $item->get());

        sleep(2);

        $item = $adapter1->getItem('key1');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value1', $item->get());

        $item = $adapter2->getItem('key1');
        $this->assertFalse($item->isHit());

        sleep(2);

        $item = $adapter1->getItem('key1');
        $this->assertTrue($item->isHit());
        $this->assertEquals('value1', $item->get());

        sleep(2);

        $item = $adapter1->getItem('key1');
        $this->assertFalse($item->isHit());
    }

    private function getPruneableMock(): AdapterInterface
    {
        $pruneable = $this->createMock(PrunableAdapter::class);

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(true);

        return $pruneable;
    }

    private function getFailingPruneableMock(): AdapterInterface
    {
        $pruneable = $this->createMock(PrunableAdapter::class);

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
