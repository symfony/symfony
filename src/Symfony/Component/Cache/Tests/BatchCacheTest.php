<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\BatchCache;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class BatchCacheTest extends TestCase
{
    public function testEmptyKeys()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItems');
        $pool->expects($this->never())->method('saveDeferred');
        $pool->expects($this->never())->method('commit');

        $this->assertSame([], (new BatchCache($pool))->get([], static function () {
            self::fail('The callback must not run without misses.');
        }));
    }

    public function testAllHits()
    {
        $first = $this->createItem('first', true, null);
        $second = $this->createItem('second', true, false);
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItems')->with(['second', 'first'])->willReturn(['first' => $first, 'second' => $second]);
        $pool->expects($this->never())->method('saveDeferred');
        $pool->expects($this->never())->method('commit');

        $result = (new BatchCache($pool))->get(['second', 'first'], static function () {
            self::fail('The callback must not run without misses.');
        });

        $this->assertSame(['second' => false, 'first' => null], $result);
    }

    public function testMixedHitsAndMisses()
    {
        $first = $this->createItem('first', false);
        $cached = $this->createItem('cached', true, 'cached value');
        $last = $this->createItem('last', false);
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItems')->with(['first', 'cached', 'last'])->willReturn(['last' => $last, 'cached' => $cached, 'first' => $first]);
        $saved = [];
        $pool->expects($this->exactly(2))->method('saveDeferred')->willReturnCallback(static function (CacheItemInterface $item) use (&$saved) {
            $saved[$item->getKey()] = $item->get();

            return true;
        });
        $pool->expects($this->once())->method('commit')->willReturnCallback(function () use (&$saved) {
            $this->assertSame(['first' => null, 'last' => false], $saved);

            return true;
        });
        $calls = 0;

        $result = (new BatchCache($pool))->get(['first', 'cached', 'last'], function (array $items) use ($first, $last, &$calls) {
            ++$calls;
            $this->assertSame(['first' => $first, 'last' => $last], $items);

            return ['last' => false, 'first' => null];
        });

        $this->assertSame(1, $calls);
        $this->assertSame(['first' => null, 'cached' => 'cached value', 'last' => false], $result);
    }

    public function testDuplicateAndNumericKeys()
    {
        $keys = ['123', '0123', '-1', '0'];
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->createItem($key, false);
        }
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItems')->with($keys)->willReturn(array_reverse($items, true));
        $pool->expects($this->exactly(4))->method('saveDeferred')->willReturn(true);
        $pool->expects($this->once())->method('commit')->willReturn(true);

        $result = (new BatchCache($pool))->get(['123', '0123', '123', '-1', '0', '-1'], function (array $missing) use ($items) {
            $this->assertSame($items, $missing);

            return [0 => 'zero', -1 => 'negative', '0123' => 'leading zero', 123 => 'integer string'];
        });

        $this->assertSame([123 => 'integer string', '0123' => 'leading zero', -1 => 'negative', 0 => 'zero'], $result);
    }

    #[DataProvider('provideInvalidKeys')]
    public function testInvalidKeysAreRejectedBeforeLookup(mixed $key)
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItems');
        $pool->expects($this->never())->method('saveDeferred');
        $pool->expects($this->never())->method('commit');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache key');

        (new BatchCache($pool))->get(['123', 'valid', $key], static function () {
            self::fail('The callback must not run for invalid keys.');
        });
    }

    public static function provideInvalidKeys(): iterable
    {
        yield 'empty string' => [''];
        yield 'reserved character' => ['invalid:key'];
        yield 'integer duplicate' => [123];
        yield 'null' => [null];
        yield 'boolean' => [true];
        yield 'float' => [1.5];
        yield 'array' => [[]];
        yield 'object' => [new \stdClass()];
    }

    #[DataProvider('provideInvalidCallbackResults')]
    public function testInvalidCallbackResultsAreRejectedBeforeSaving(mixed $values)
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItems')->with(['first', 'second'])->willReturn([
            'first' => $this->createItem('first', false),
            'second' => $this->createItem('second', false),
        ]);
        $pool->expects($this->never())->method('saveDeferred');
        $pool->expects($this->never())->method('commit');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The callback must return');

        (new BatchCache($pool))->get(['first', 'second'], static fn () => $values);
    }

    public static function provideInvalidCallbackResults(): iterable
    {
        yield 'empty array' => [[]];
        yield 'missing key' => [['first' => 'value']];
        yield 'unexpected key' => [['first' => 'value', 'second' => 'value', 'third' => 'value']];
        yield 'replaced key' => [['first' => 'value', 'third' => 'value']];
        yield 'numeric list' => [['first', 'second']];
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'string' => ['value'];
        yield 'iterator' => [new \ArrayIterator(['first' => 'value', 'second' => 'value'])];
    }

    public function testCallbackCannotReplacePendingItems()
    {
        $item = $this->createItem('first', false);
        $replacement = $this->createItem('replacement', false);
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItems')->with(['first'])->willReturn(['first' => $item]);
        $pool->expects($this->once())->method('saveDeferred')->with($this->identicalTo($item))->willReturn(true);
        $pool->expects($this->once())->method('commit')->willReturn(true);

        $result = (new BatchCache($pool))->get(['first'], static function (array &$items) use ($replacement) {
            $items = ['replacement' => $replacement];

            return ['first' => 'value'];
        });

        $this->assertSame(['first' => 'value'], $result);
        $this->assertSame('value', $item->get());
    }

    public function testCallbackFailureDoesNotLeaveDeferredWrites()
    {
        $pool = new TagAwareAdapter(new ArrayAdapter(0, false));
        $pool->saveDeferred($pool->getItem('existing')->set('pending value'));
        $failure = new \RuntimeException('Loading failed.');

        try {
            (new BatchCache($pool))->get(['first', 'second'], static function (array $items) use ($failure) {
                $items['first']->set('partial value');

                throw $failure;
            });
            $this->fail('The callback exception must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame($failure, $e);
        }

        $pool->commit();

        $this->assertSame('pending value', $pool->getItem('existing')->get());
        $this->assertFalse($pool->hasItem('first'));
        $this->assertFalse($pool->hasItem('second'));
    }

    #[DataProvider('provideFalseStorageResults')]
    public function testFalseStorageResults(bool $deferredResult, bool $commitResult)
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItems')->willReturn([
            'first' => $this->createItem('first', false),
            'second' => $this->createItem('second', false),
        ]);
        $pool->expects($this->exactly(2))->method('saveDeferred')->willReturn($deferredResult);
        $pool->expects($this->once())->method('commit')->willReturn($commitResult);

        $this->assertSame(['first' => 'value', 'second' => null], (new BatchCache($pool))->get(['first', 'second'], static fn () => ['first' => 'value', 'second' => null]));
    }

    public static function provideFalseStorageResults(): iterable
    {
        yield 'deferred save fails' => [false, true];
        yield 'commit fails' => [true, false];
        yield 'both fail' => [false, false];
    }

    #[DataProvider('provideFailingPoolMethods')]
    public function testPoolExceptionsPropagate(string $method)
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $failure = new \RuntimeException('Cache operation failed.');
        $pool->expects($this->once())->method($method)->willThrowException($failure);
        if ('getItems' !== $method) {
            $pool->method('getItems')->willReturn(['first' => $this->createItem('first', false)]);
        }
        if ('commit' === $method) {
            $pool->expects($this->once())->method('saveDeferred')->willReturn(true);
        } else {
            $pool->expects($this->never())->method('commit');
        }
        $this->expectExceptionObject($failure);

        (new BatchCache($pool))->get(['first'], static fn () => ['first' => 'value']);
    }

    public static function provideFailingPoolMethods(): iterable
    {
        yield ['getItems'];
        yield ['saveDeferred'];
        yield ['commit'];
    }

    public function testCallbackCanSetExpiration()
    {
        $clock = new MockClock();
        $pool = new ArrayAdapter(0, false, 0, 0, $clock);

        $result = (new BatchCache($pool))->get(['short', 'long'], static function (array $items) {
            $items['short']->expiresAfter(10);
            $items['long']->expiresAfter(30);

            return ['short' => 'short value', 'long' => 'long value'];
        });

        $this->assertSame(['short' => 'short value', 'long' => 'long value'], $result);
        $this->assertTrue($pool->hasItem('short'));
        $clock->sleep(11);
        $this->assertFalse($pool->hasItem('short'));
        $this->assertSame('long value', $pool->getItem('long')->get());
    }

    public function testCallbackCanTagItems()
    {
        $storage = new ArrayAdapter(0, false);
        $pool = new TagAwareAdapter($storage);
        $pool->saveDeferred($pool->getItem('existing')->set('pending value'));
        $this->assertFalse($storage->hasItem('existing'));

        $result = (new BatchCache($pool))->get(['product', 'other'], function (array $items) {
            $this->assertInstanceOf(ItemInterface::class, $items['product']);
            $items['product']->tag('catalogue');

            return ['product' => 'product value', 'other' => 'other value'];
        });

        $this->assertSame(['product' => 'product value', 'other' => 'other value'], $result);
        $this->assertSame('pending value', $storage->getItem('existing')->get());
        $this->assertSame('product value', $pool->getItem('product')->get());
        $pool->invalidateTags(['catalogue']);
        $this->assertFalse($pool->hasItem('product'));
        $this->assertSame('other value', $pool->getItem('other')->get());
    }

    #[DataProvider('provideKeysWithoutMisses')]
    public function testLockKeyIsNotUsedWithoutMisses(array $keys)
    {
        $pool = $this->createMockForIntersectionOfInterfaces([CacheItemPoolInterface::class, CacheInterface::class]);
        $pool->expects($this->never())->method('get');
        $pool->expects($this->never())->method('saveDeferred');
        $pool->expects($this->never())->method('commit');
        if ($keys) {
            $pool->expects($this->once())->method('getItems')->with($keys)->willReturn(['first' => $this->createItem('first', true, 'cached value')]);
        } else {
            $pool->expects($this->never())->method('getItems');
        }

        $result = (new BatchCache($pool))->get($keys, static function () {
            self::fail('The callback must not run without misses.');
        }, 'batch-lock');

        $this->assertSame($keys ? ['first' => 'cached value'] : [], $result);
    }

    public static function provideKeysWithoutMisses(): iterable
    {
        yield 'no keys' => [[]];
        yield 'all hits' => [['first']];
    }

    public function testLockKeyRequiresCacheContracts()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->never())->method('getItems');
        $pool->expects($this->never())->method('saveDeferred');
        $pool->expects($this->never())->method('commit');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(CacheInterface::class);

        (new BatchCache($pool))->get(['first'], static fn () => ['first' => 'value'], 'batch-lock');
    }

    #[DataProvider('provideInvalidLockKeys')]
    public function testInvalidLockKeyIsRejectedBeforeLookup(string $lockKey)
    {
        $pool = $this->createMockForIntersectionOfInterfaces([CacheItemPoolInterface::class, CacheInterface::class]);
        $pool->expects($this->never())->method('get');
        $pool->expects($this->never())->method('getItems');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache key');

        (new BatchCache($pool))->get([], static fn () => [], $lockKey);
    }

    public static function provideInvalidLockKeys(): iterable
    {
        yield 'empty string' => [''];
        yield 'reserved character' => ['invalid:key'];
    }

    public function testMissesAreReadAgainUnderTheLock()
    {
        $later = $this->createItem('later', false);
        $pool = $this->createMockForIntersectionOfInterfaces([CacheItemPoolInterface::class, CacheInterface::class]);
        $pool->expects($this->exactly(2))->method('getItems')->with(['123', 'cached', 'later'])->willReturnOnConsecutiveCalls([
            123 => $this->createItem('123', false),
            'cached' => $this->createItem('cached', true, 'old value'),
            'later' => $this->createItem('later', false),
        ], [
            'later' => $later,
            'cached' => $this->createItem('cached', true, 'updated value'),
            123 => $this->createItem('123', true, 'another request'),
        ]);
        $pool->expects($this->once())->method('saveDeferred')->with($this->identicalTo($later))->willReturn(true);
        $committed = false;
        $pool->expects($this->once())->method('commit')->willReturnCallback(static function () use (&$committed) {
            return $committed = true;
        });
        $pool->expects($this->once())->method('get')->with('batch-lock', $this->anything(), \INF)->willReturnCallback(function ($key, $callback) use (&$committed) {
            $save = true;
            $result = $callback($this->createItem($key, false), $save);
            $this->assertFalse($save);
            $this->assertTrue($committed);

            return $result;
        });
        $calls = 0;

        $result = (new BatchCache($pool))->get(['123', 'cached', '123', 'later'], function (array $items) use ($later, &$calls) {
            ++$calls;
            $this->assertSame(['later' => $later], $items);

            return ['later' => null];
        }, 'batch-lock');

        $this->assertSame(1, $calls);
        $this->assertSame([123 => 'another request', 'cached' => 'updated value', 'later' => null], $result);
    }

    public function testLockedCallbackFailureDoesNotSave()
    {
        $pool = $this->createMockForIntersectionOfInterfaces([CacheItemPoolInterface::class, CacheInterface::class]);
        $pool->expects($this->exactly(2))->method('getItems')->with(['first'])->willReturn(['first' => $this->createItem('first', false)]);
        $pool->expects($this->never())->method('saveDeferred');
        $pool->expects($this->never())->method('commit');
        $pool->expects($this->once())->method('get')->with('batch-lock', $this->anything(), \INF)->willReturnCallback(function ($key, $callback) {
            $save = true;
            try {
                return $callback($this->createItem($key, false), $save);
            } finally {
                $this->assertFalse($save);
            }
        });
        $failure = new \RuntimeException('Loading failed.');
        $this->expectExceptionObject($failure);

        (new BatchCache($pool))->get(['first'], static function () use ($failure) {
            throw $failure;
        }, 'batch-lock');
    }

    public function testLockKeyDoesNotCacheBatchResults()
    {
        $clock = new MockClock();
        $pool = new TagAwareAdapter(new ArrayAdapter(0, false, 0, 0, $clock), null, 0);
        $pool->save($pool->getItem('batch-lock')->set('existing value'));
        $cache = new BatchCache($pool);
        $calls = 0;
        $callback = static function (array $items) use (&$calls) {
            ++$calls;
            foreach ($items as $key => $item) {
                $item->expiresAfter('short' === $key ? 10 : 30);
                if ('tagged' === $key) {
                    $item->tag('catalogue');
                }
            }

            return array_map(static fn ($item) => $item->getKey().'-'.$calls, $items);
        };

        $this->assertSame(['short' => 'short-1', 'tagged' => 'tagged-1'], $cache->get(['short', 'tagged'], $callback, 'batch-lock'));
        $this->assertSame('existing value', $pool->getItem('batch-lock')->get());
        $clock->sleep(11);
        $this->assertFalse($pool->hasItem('short'));
        $this->assertTrue($pool->hasItem('tagged'));
        $pool->invalidateTags(['catalogue']);

        $this->assertSame(['short' => 'short-2', 'tagged' => 'tagged-2'], $cache->get(['short', 'tagged'], $callback, 'batch-lock'));
        $this->assertSame(2, $calls);
        $this->assertSame('existing value', $pool->getItem('batch-lock')->get());
    }

    private function createItem(string $key, bool $hit, mixed $value = null): CacheItemInterface
    {
        $item = $this->createStub(CacheItemInterface::class);
        $item->method('getKey')->willReturn($key);
        $item->method('isHit')->willReturn($hit);
        $item->method('get')->willReturnCallback(static function () use (&$value) {
            return $value;
        });
        $item->method('set')->willReturnCallback(static function ($newValue) use (&$value, $item) {
            $value = $newValue;

            return $item;
        });

        return $item;
    }
}
