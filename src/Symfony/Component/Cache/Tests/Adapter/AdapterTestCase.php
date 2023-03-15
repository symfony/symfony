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

use Cache\IntegrationTests\CachePoolTest;
use PHPUnit\Framework\Assert;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Contracts\Cache\CallbackInterface;

abstract class AdapterTestCase extends CachePoolTest
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!\array_key_exists('testPrune', $this->skippedTests) && !$this->createCachePool() instanceof PruneableInterface) {
            $this->skippedTests['testPrune'] = 'Not a pruneable cache pool.';
        }

        try {
            \assert(false === true, new \Exception());
            $this->skippedTests['testGetItemInvalidKeys'] =
            $this->skippedTests['testGetItemsInvalidKeys'] =
            $this->skippedTests['testHasItemInvalidKeys'] =
            $this->skippedTests['testDeleteItemInvalidKeys'] =
            $this->skippedTests['testDeleteItemsInvalidKeys'] = 'Keys are checked only when assert() is enabled.';
        } catch (\Exception $e) {
        }
    }

    public function testGet()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool();
        $cache->clear();

        $value = mt_rand();

        $this->assertSame($value, $cache->get('foo', function (CacheItem $item) use ($value) {
            $this->assertSame('foo', $item->getKey());

            return $value;
        }));

        $item = $cache->getItem('foo');
        $this->assertSame($value, $item->get());

        $isHit = true;
        $this->assertSame($value, $cache->get('foo', function (CacheItem $item) use (&$isHit) { $isHit = false; }, 0));
        $this->assertTrue($isHit);

        $this->assertNull($cache->get('foo', function (CacheItem $item) use (&$isHit, $value) {
            $isHit = false;
            $this->assertTrue($item->isHit());
            $this->assertSame($value, $item->get());
        }, \INF));
        $this->assertFalse($isHit);

        $this->assertSame($value, $cache->get('bar', new class($value) implements CallbackInterface {
            private $value;

            public function __construct(int $value)
            {
                $this->value = $value;
            }

            public function __invoke(CacheItemInterface $item, bool &$save): mixed
            {
                Assert::assertSame('bar', $item->getKey());

                return $this->value;
            }
        }));
    }

    public function testRecursiveGet()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool(0, __FUNCTION__);

        $v = $cache->get('k1', function () use (&$counter, $cache) {
            $cache->get('k2', function () use (&$counter) { return ++$counter; });
            $v = $cache->get('k2', function () use (&$counter) { return ++$counter; }); // ensure the callback is called once

            return $v;
        });

        $this->assertSame(1, $counter);
        $this->assertSame(1, $v);
        $this->assertSame(1, $cache->get('k2', fn () => 2));
    }

    public function testDontSaveWhenAskedNotTo()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool(0, __FUNCTION__);

        $v1 = $cache->get('some-key', function ($item, &$save) {
            $save = false;

            return 1;
        });
        $this->assertSame($v1, 1);

        $v2 = $cache->get('some-key', fn () => 2);
        $this->assertSame($v2, 2, 'First value was cached and should not have been');

        $v3 = $cache->get('some-key', function () {
            $this->fail('Value should have come from cache');
        });
        $this->assertSame($v3, 2);
    }

    public function testGetMetadata()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool(0, __FUNCTION__);

        $cache->deleteItem('foo');
        $cache->get('foo', function ($item) {
            $item->expiresAfter(10);
            usleep(999000);

            return 'bar';
        });

        $item = $cache->getItem('foo');

        $metadata = $item->getMetadata();
        $this->assertArrayHasKey(CacheItem::METADATA_CTIME, $metadata);
        $this->assertEqualsWithDelta(999, $metadata[CacheItem::METADATA_CTIME], 150);
        $this->assertArrayHasKey(CacheItem::METADATA_EXPIRY, $metadata);
        $this->assertEqualsWithDelta(9 + time(), $metadata[CacheItem::METADATA_EXPIRY], 1);
    }

    public function testDefaultLifeTime()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool(2);

        $item = $cache->getItem('key.dlt');
        $item->set('value');
        $cache->save($item);
        sleep(1);

        $item = $cache->getItem('key.dlt');
        $this->assertTrue($item->isHit());

        sleep(2);
        $item = $cache->getItem('key.dlt');
        $this->assertFalse($item->isHit());
    }

    public function testExpiration()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool();
        $cache->save($cache->getItem('k1')->set('v1')->expiresAfter(2));
        $cache->save($cache->getItem('k2')->set('v2')->expiresAfter(366 * 86400));

        sleep(3);
        $item = $cache->getItem('k1');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit() is false.");

        $item = $cache->getItem('k2');
        $this->assertTrue($item->isHit());
        $this->assertSame('v2', $item->get());
    }

    public function testNotUnserializable()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $cache->save($item->set(new NotUnserializable()));

        $item = $cache->getItem('foo');
        $this->assertFalse($item->isHit());

        foreach ($cache->getItems(['foo']) as $item) {
        }
        $cache->save($item->set(new NotUnserializable()));

        foreach ($cache->getItems(['foo']) as $item) {
        }
        $this->assertFalse($item->isHit());
    }

    public function testPrune()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        if (!method_exists($this, 'isPruned')) {
            $this->fail('Test classes for pruneable caches must implement `isPruned($cache, $name)` method.');
        }

        /** @var PruneableInterface|CacheItemPoolInterface $cache */
        $cache = $this->createCachePool();

        $doSet = function ($name, $value, \DateInterval $expiresAfter = null) use ($cache) {
            $item = $cache->getItem($name);
            $item->set($value);

            if ($expiresAfter) {
                $item->expiresAfter($expiresAfter);
            }

            $cache->save($item);
        };

        $doSet('foo', 'foo-val', new \DateInterval('PT05S'));
        $doSet('bar', 'bar-val', new \DateInterval('PT10S'));
        $doSet('baz', 'baz-val', new \DateInterval('PT15S'));
        $doSet('qux', 'qux-val', new \DateInterval('PT20S'));

        sleep(30);
        $this->assertTrue($cache->prune());
        $this->assertTrue($this->isPruned($cache, 'foo'));
        $this->assertTrue($this->isPruned($cache, 'bar'));
        $this->assertTrue($this->isPruned($cache, 'baz'));
        $this->assertTrue($this->isPruned($cache, 'qux'));

        $doSet('foo', 'foo-val');
        $doSet('bar', 'bar-val', new \DateInterval('PT20S'));
        $doSet('baz', 'baz-val', new \DateInterval('PT40S'));
        $doSet('qux', 'qux-val', new \DateInterval('PT80S'));

        $this->assertTrue($cache->prune());
        $this->assertFalse($this->isPruned($cache, 'foo'));
        $this->assertFalse($this->isPruned($cache, 'bar'));
        $this->assertFalse($this->isPruned($cache, 'baz'));
        $this->assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $this->assertTrue($cache->prune());
        $this->assertFalse($this->isPruned($cache, 'foo'));
        $this->assertTrue($this->isPruned($cache, 'bar'));
        $this->assertFalse($this->isPruned($cache, 'baz'));
        $this->assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $this->assertTrue($cache->prune());
        $this->assertFalse($this->isPruned($cache, 'foo'));
        $this->assertTrue($this->isPruned($cache, 'baz'));
        $this->assertFalse($this->isPruned($cache, 'qux'));

        sleep(30);
        $this->assertTrue($cache->prune());
        $this->assertFalse($this->isPruned($cache, 'foo'));
        $this->assertTrue($this->isPruned($cache, 'qux'));
    }

    public function testClearPrefix()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool(0, __FUNCTION__);
        $cache->clear();

        $item = $cache->getItem('foobar');
        $cache->save($item->set(1));

        $item = $cache->getItem('barfoo');
        $cache->save($item->set(2));

        $cache->clear('foo');
        $this->assertFalse($cache->hasItem('foobar'));
        $this->assertTrue($cache->hasItem('barfoo'));
    }

    public function testWeirdDataMatchingMetadataWrappedValues()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $cache = $this->createCachePool(0, __FUNCTION__);
        $cache->clear();

        $item = $cache->getItem('foobar');

        // it should be an array containing only one element
        // with key having a strlen of 10.
        $weirdDataMatchingMedatataWrappedValue = [
            1234567890 => [
                1,
            ],
        ];

        $cache->save($item->set($weirdDataMatchingMedatataWrappedValue));

        $this->assertTrue($cache->hasItem('foobar'));
    }

    public function testNullByteInKey()
    {
        $cache = $this->createCachePool(0, __FUNCTION__);

        $cache->save($cache->getItem("a\0b")->set(123));

        $this->assertSame(123, $cache->getItem("a\0b")->get());
    }

    public function testNumericKeysWorkAfterMemoryLeakPrevention()
    {
        $cache = $this->createCachePool(0, __FUNCTION__);

        for ($i = 0; $i < 1001; ++$i) {
            $cacheItem = $cache->getItem((string) $i);
            $cacheItem->set('value-'.$i);
            $cache->save($cacheItem);
        }

        $this->assertEquals('value-50', $cache->getItem((string) 50)->get());
    }
}

class NotUnserializable
{
    public function __wakeup()
    {
        throw new \Exception(__CLASS__);
    }
}
