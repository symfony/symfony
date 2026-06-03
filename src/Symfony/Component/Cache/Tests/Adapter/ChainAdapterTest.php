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

use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Tests\Fixtures\ExternalAdapter;
use Symfony\Component\Cache\Tests\Fixtures\PrunableAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
#[Group('time-sensitive')]
class ChainAdapterTest extends AdapterTestCase
{
    public function createCachePool(int $defaultLifetime = 0, ?string $testMethod = null): CacheItemPoolInterface
    {
        if ('testGetMetadata' === $testMethod) {
            return new ChainAdapter([new FilesystemAdapter('a', $defaultLifetime), new FilesystemAdapter('b', $defaultLifetime)], $defaultLifetime);
        }

        return new ChainAdapter([new ArrayAdapter($defaultLifetime), new ExternalAdapter($defaultLifetime), new FilesystemAdapter('', $defaultLifetime)], $defaultLifetime);
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    public function testEmptyAdaptersException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one adapter must be specified.');
        new ChainAdapter([]);
    }

    public function testInvalidAdapterException()
    {
        $this->expectException(InvalidArgumentException::class);
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
            $this->createStub(AdapterInterface::class),
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

    public function testItemExpiryIsPreservedWhenPropagatedToPreviousAdapters()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $adapter1 = new ArrayAdapter(100);
        $adapter2 = new ArrayAdapter(100);

        $cache = new ChainAdapter([$adapter1, $adapter2], 100);

        // Save with an explicit 2-second TTL
        $cache->save($cache->getItem('key')->expiresAfter(2)->set('value'));

        // Simulate adapter1 miss
        $adapter1->clear();
        $this->assertFalse($adapter1->hasItem('key'));
        $this->assertTrue($adapter2->hasItem('key'));

        // This should propagate the item from adapter2 to adapter1,
        // preserving the original 2-second TTL (not the 100-second defaultLifetime)
        $cache->getItem('key');
        $this->assertTrue($adapter1->hasItem('key'));

        sleep(3);

        $this->assertFalse($adapter2->getItem('key')->isHit(), 'Item should have expired in adapter2');
        $this->assertFalse($adapter1->getItem('key')->isHit(), 'Item should have expired in adapter1 with original TTL, not defaultLifetime');
    }

    public function testItemExpiryIsPreservedWhenPropagatedToPreviousAdaptersUsingGetMethod()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $adapter1 = new ArrayAdapter(100);
        $adapter2 = new ArrayAdapter(100);

        $cache = new ChainAdapter([$adapter1, $adapter2], 100);

        // Save with an explicit 2-second TTL via get() callback
        $cache->get('key', static function (ItemInterface $item) {
            $item->expiresAfter(2);

            return 'value';
        });

        // Simulate adapter1 miss
        $adapter1->clear();
        $this->assertFalse($adapter1->hasItem('key'));
        $this->assertTrue($adapter2->hasItem('key'));

        // This should propagate the item from adapter2 to adapter1,
        // preserving the original 2-second TTL (not the 100-second defaultLifetime)
        $cache->get('key', function () {
            $this->fail('Callback should not be called when item exists in adapter2');
        });
        $this->assertTrue($adapter1->hasItem('key'));

        sleep(3);

        $this->assertFalse($adapter2->getItem('key')->isHit(), 'Item should have expired in adapter2');
        $this->assertFalse($adapter1->getItem('key')->isHit(), 'Item should have expired in adapter1 with original TTL, not defaultLifetime');
    }

    public function testExpirationOnAllAdapters()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $itemValidator = function (CacheItem $item) {
            $refl = new \ReflectionObject($item);
            $propExpiry = $refl->getProperty('expiry');
            $expiry = $propExpiry->getValue($item);
            $this->assertGreaterThan(10, $expiry - time(), 'Item should be saved with the given ttl, not the default for the adapter.');

            return true;
        };

        $adapter1 = $this->getMockBuilder(FilesystemAdapter::class)
            ->setConstructorArgs(['', 2])
            ->onlyMethods(['save'])
            ->getMock();
        $adapter1->expects($this->once())
            ->method('save')
            ->with($this->callback($itemValidator))
            ->willReturn(true);

        $adapter2 = $this->getMockBuilder(FilesystemAdapter::class)
            ->setConstructorArgs(['', 4])
            ->onlyMethods(['save'])
            ->getMock();
        $adapter2->expects($this->once())
            ->method('save')
            ->with($this->callback($itemValidator))
            ->willReturn(true);

        $cache = new ChainAdapter([$adapter1, $adapter2], 6);
        $cache->get('test_key', static function (ItemInterface $item) {
            $item->expiresAfter(15);

            return 'chain';
        });
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

    public function testSetCallbackWrapperPropagation()
    {
        $adapter1 = new ArrayAdapter();
        $adapter2 = new FilesystemAdapter();

        $chain = new ChainAdapter([$adapter1, $adapter2]);

        $callbackWrapperCalled = false;
        $customWrapper = static function (callable $callback, CacheItem $item, bool &$save) use (&$callbackWrapperCalled) {
            $callbackWrapperCalled = true;

            return $callback($item, $save);
        };

        $chain->setCallbackWrapper($customWrapper);

        $chain->delete('test-callback-wrapper');

        $result = $chain->get('test-callback-wrapper', static fn () => 'computed-value');

        $this->assertTrue($callbackWrapperCalled);
        $this->assertSame('computed-value', $result);
    }
}
