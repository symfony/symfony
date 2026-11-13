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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\LockRegistry;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Tests\Fixtures\PrunableAdapter;
use Symfony\Component\Cache\Tests\Fixtures\ResettableAdapter;
use Symfony\Component\Cache\Tests\Fixtures\ShutdownAwareAdapter;
use Symfony\Component\Filesystem\Filesystem;

#[Group('time-sensitive')]
class TagAwareAdapterTest extends AdapterTestCase
{
    use TagAwareTestTrait;

    public function createCachePool($defaultLifetime = 0): CacheItemPoolInterface
    {
        return new TagAwareAdapter(new FilesystemAdapter('', $defaultLifetime));
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    public function testPrune()
    {
        $cache = new TagAwareAdapter($this->getPruneableMock());
        $this->assertTrue($cache->prune());

        $cache = new TagAwareAdapter($this->createStub(AdapterInterface::class));
        $this->assertFalse($cache->prune());

        $cache = new TagAwareAdapter($this->getFailingPruneableMock());
        $this->assertFalse($cache->prune());
    }

    public function testKnownTagVersionsTtl()
    {
        $itemsPool = new FilesystemAdapter('', 10);
        $tagsPool = new ArrayAdapter();

        $pool = new TagAwareAdapter($itemsPool, $tagsPool, 10);

        $item = $pool->getItem('foo');
        $item->tag(['baz']);
        $item->expiresAfter(100);

        $tag = $tagsPool->getItem('baz'.TagAwareAdapter::TAGS_PREFIX);
        $tagsPool->save($tag->set(10));

        $pool->save($item);
        $this->assertTrue($pool->getItem('foo')->isHit());

        $tagsPool->deleteItem('baz'.TagAwareAdapter::TAGS_PREFIX); // tag invalidation

        $this->assertTrue($pool->getItem('foo')->isHit()); // known tag version is used

        sleep(10);

        $this->assertTrue($pool->getItem('foo')->isHit()); // known tag version is still used

        sleep(1);

        $this->assertFalse($pool->getItem('foo')->isHit()); // known tag version has expired
    }

    public function testGetItemsWithNumericStringKeys()
    {
        $adapter = new TagAwareAdapter(new ArrayAdapter());

        $item = $adapter->getItem('123');
        $item->set('foo-val');
        $adapter->save($item);

        $keys = [];
        foreach ($adapter->getItems(['123', '456']) as $key => $item) {
            $keys[] = $key;
            $this->assertSame($key, $item->getKey());
        }

        $this->assertSame(['123', '456'], $keys);
    }

    public function testInvalidateTagsWithArrayAdapter()
    {
        $adapter = new TagAwareAdapter(new ArrayAdapter());

        $item = $adapter->getItem('foo');

        $this->assertFalse($item->isHit());

        $item->tag('bar');
        $item->expiresAfter(100);
        $adapter->save($item);

        $this->assertTrue($adapter->getItem('foo')->isHit());

        $adapter->invalidateTags(['bar']);

        $this->assertFalse($adapter->getItem('foo')->isHit());
    }

    #[DataProvider('provideInvalidatedComputations')]
    public function testComputationsKeepKnownTagVersions(bool $lock, bool $sameAdapter, bool $replacement, bool $refresh, bool $recompute)
    {
        $storage = new ArrayAdapter();
        $adapter = new TagAwareAdapter($storage, null, 0);
        $invalidator = new TagAwareAdapter($storage, null, 0);
        $adapter->save($adapter->getItem($recompute ? 'computed' : 'seed')->set('old')->tag('product'));
        if ($recompute) {
            $adapter = new TagAwareAdapter($storage, null, 0);
        }
        if ($lock) {
            $adapter->setCallbackWrapper(LockRegistry::compute(...));
        }

        $this->assertSame('old', $adapter->get('computed', static function (CacheItem $item) use ($adapter, $invalidator, $sameAdapter, $replacement, $refresh) {
            ($sameAdapter ? $adapter : $invalidator)->invalidateTags(['product']);
            if ($replacement) {
                $invalidator->save($invalidator->getItem('replacement')->set('new')->tag('product'));
            }
            if ($refresh) {
                $adapter->getItem('replacement');
            }
            $item->tag('product');

            return 'old';
        }, $recompute ? \INF : null));

        $this->assertTrue($storage->getItem('computed')->isHit());
        $this->assertFalse((new TagAwareAdapter($storage, null, 0))->getItem('computed')->isHit());
    }

    public static function provideInvalidatedComputations(): iterable
    {
        yield 'deleted version' => [false, false, false, false, false];
        yield 'replacement version' => [false, false, true, false, false];
        yield 'refreshed local versions' => [false, false, true, true, false];
        yield 'same adapter' => [false, true, true, false, false];
        yield 'forced recomputation' => [false, false, true, false, true];
        yield 'stampede lock' => [true, false, true, true, false];
    }

    public function testComputationPreservesCallbackAndPopulatesUnknownTags()
    {
        $adapter = new TagAwareAdapter(new ArrayAdapter(), null, 0);
        $callback = static function (CacheItem $item) {
            $item->tag('product');

            return 'value';
        };
        $adapter->setCallbackWrapper(function (callable $actual, CacheItem $item, bool &$save) use ($callback) {
            $this->assertSame($callback, $actual);

            return $actual($item, $save);
        });

        $this->assertSame('value', $adapter->get('cold', $callback));
        $this->assertSame('value', $adapter->getItem('cold')->get());
        $this->assertSame('value', $adapter->get('warm', $callback));
        $this->assertSame('value', $adapter->getItem('warm')->get());
    }

    public function testExplicitDeferredItemsUseFreshVersionsDuringComputation()
    {
        $storage = new ArrayAdapter();
        $adapter = new TagAwareAdapter($storage, null, 0);
        $adapter->save($adapter->getItem('seed')->tag('product'));

        $adapter->get('computed', static function (CacheItem $item) use ($adapter) {
            $adapter->saveDeferred($adapter->getItem('explicit')->set('new')->tag('product'));
            $adapter->invalidateTags(['product']);
            $item->tag('product');

            return 'old';
        });

        $reader = new TagAwareAdapter($storage, null, 0);
        $this->assertSame('new', $reader->getItem('explicit')->get());
        $this->assertFalse($reader->getItem('computed')->isHit());
    }

    public function testFailedComputationSaveKeepsSnapshotForCommitRetry()
    {
        $storage = new class extends ArrayAdapter {
            public bool $fail = false;

            public function saveDeferred(\Psr\Cache\CacheItemInterface $item): bool
            {
                return !$this->fail && parent::saveDeferred($item);
            }
        };
        $adapter = new TagAwareAdapter($storage, null, 0);
        $adapter->save($adapter->getItem('seed')->tag('product'));

        $adapter->get('computed', static function (CacheItem $item) use ($adapter, $storage) {
            $adapter->invalidateTags(['product']);
            $storage->fail = true;
            $item->tag('product');

            return 'old';
        }, null, $metadata);
        $this->assertTrue($metadata[CacheItem::METADATA_SAVE_FAILED]);
        $storage->fail = false;
        $this->assertTrue($adapter->commit());
        $this->assertTrue($storage->getItem('computed')->isHit());
        $this->assertFalse((new TagAwareAdapter($storage, null, 0))->getItem('computed')->isHit());
    }

    public function testExplicitSaveAfterComputationUsesFreshVersions()
    {
        $storage = new ArrayAdapter();
        $adapter = new TagAwareAdapter($storage, null, 0);
        $adapter->save($adapter->getItem('seed')->tag('product'));
        $computed = null;
        $adapter->get('computed', static function (CacheItem $item) use ($adapter, &$computed) {
            $adapter->invalidateTags(['product']);
            $computed = $item;
            $item->tag('product');

            return 'old';
        });

        $adapter->save($computed->set('new'));
        $this->assertSame('new', (new TagAwareAdapter($storage, null, 0))->getItem('computed')->get());
    }

    public function testResetDuringComputationPreservesSnapshot()
    {
        $storage = new ArrayAdapter();
        $adapter = new TagAwareAdapter($storage, null, 0);
        $adapter->save($adapter->getItem('seed')->tag('product'));

        $adapter->get('computed', static function (CacheItem $item) use ($adapter) {
            $adapter->invalidateTags(['product']);
            $adapter->reset();
            $item->tag('product');

            return 'old';
        });

        $this->assertTrue($storage->getItem('computed')->isHit());
        $this->assertFalse((new TagAwareAdapter($storage, null, 0))->getItem('computed')->isHit());
    }

    #[DataProvider('provideNestedComputations')]
    public function testNestedComputationsKeepSeparateSnapshots(bool $subNamespace)
    {
        $storage = new ArrayAdapter();
        $adapter = new TagAwareAdapter($storage, null, 0);
        $nested = $subNamespace ? $adapter->withSubNamespace('nested') : $adapter;
        $adapter->save($adapter->getItem('seed')->tag('product'));

        $adapter->get('outer', static function (CacheItem $item) use ($adapter, $nested) {
            $adapter->invalidateTags(['product']);
            $nested->get('inner', static function (CacheItem $item) {
                $item->tag('product');

                return 'new';
            });
            $item->tag('product');

            return 'old';
        });

        $this->assertSame('new', $nested->getItem('inner')->get());
        $this->assertFalse((new TagAwareAdapter($storage, null, 0))->getItem('outer')->isHit());
    }

    public static function provideNestedComputations(): iterable
    {
        yield 'same namespace' => [false];
        yield 'different namespace' => [true];
    }

    public function testExplicitDeferredComputationItemUsesFreshVersions()
    {
        $storage = new ArrayAdapter();
        $adapter = new TagAwareAdapter($storage, null, 0);
        $adapter->save($adapter->getItem('seed')->tag('product'));

        $adapter->get('computed', static function (CacheItem $item, bool &$save) use ($adapter) {
            $adapter->saveDeferred($item->set('new')->tag('product'));
            $adapter->invalidateTags(['product']);
            $save = false;

            return 'new';
        });
        $adapter->commit();

        $this->assertSame('new', (new TagAwareAdapter($storage, null, 0))->getItem('computed')->get());
    }

    public function testPreparingComputationSavePreservesItemMetadata()
    {
        $adapter = new TagAwareAdapter(new ArrayAdapter());
        $adapter->setCallbackWrapper(function (callable $callback, CacheItem $item, bool &$save, AdapterInterface $pool, \Closure $setMetadata) {
            $metadata = $item->getMetadata();
            $value = $callback($item, $save);
            $setMetadata($item);
            $this->assertSame($metadata, $item->getMetadata());
            $saved = $pool->save($item->set($value));
            $save = false;
            $setMetadata($item, $saved);

            return $value;
        });

        $this->assertSame('value', $adapter->get('computed', static function (CacheItem $item) {
            $item->tag('product');

            return 'value';
        }));
        $this->assertSame('value', $adapter->getItem('computed')->get());
    }

    private function getPruneableMock(): PruneableInterface&MockObject
    {
        $pruneable = $this->createMock(PrunableAdapter::class);

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(true);

        return $pruneable;
    }

    private function getFailingPruneableMock(): PruneableInterface&MockObject
    {
        $pruneable = $this->createMock(PrunableAdapter::class);

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(false);

        return $pruneable;
    }

    #[DoesNotPerformAssertions]
    public function testToleranceForStringsAsTagVersionsCase1()
    {
        $pool = $this->createCachePool();
        $adapter = new FilesystemAdapter();

        $itemKey = 'foo';
        $tag = $adapter->getItem('bar'.TagAwareAdapter::TAGS_PREFIX);
        $adapter->save($tag->set("\x00abc\xff"));
        $item = $pool->getItem($itemKey);
        $pool->save($item->tag('bar'));
        $pool->hasItem($itemKey);
        $pool->getItem($itemKey);
    }

    #[DoesNotPerformAssertions]
    public function testToleranceForStringsAsTagVersionsCase2()
    {
        $pool = $this->createCachePool();
        $adapter = new FilesystemAdapter();

        $itemKey = 'foo';
        $tag = $adapter->getItem('bar'.TagAwareAdapter::TAGS_PREFIX);
        $adapter->save($tag->set("\x00abc\xff"));
        $item = $pool->getItem($itemKey);
        $pool->save($item->tag('bar'));
        sleep(100);
        $pool->getItem($itemKey);
        $pool->hasItem($itemKey);
    }

    #[DoesNotPerformAssertions]
    public function testToleranceForStringsAsTagVersionsCase3()
    {
        $pool = $this->createCachePool();
        $adapter = new FilesystemAdapter();

        $itemKey = 'foo';
        $adapter->deleteItem('bar'.TagAwareAdapter::TAGS_PREFIX);
        $item = $pool->getItem($itemKey);
        $pool->save($item->tag('bar'));
        $pool->getItem($itemKey);

        $tag = $adapter->getItem('bar'.TagAwareAdapter::TAGS_PREFIX);
        $adapter->save($tag->set("\x00abc\xff"));

        $pool->hasItem($itemKey);
        $pool->getItem($itemKey);
        sleep(100);
        $pool->getItem($itemKey);
        $pool->hasItem($itemKey);
    }

    #[DoesNotPerformAssertions]
    public function testToleranceForStringsAsTagVersionsCase4()
    {
        $pool = $this->createCachePool();
        $adapter = new FilesystemAdapter();

        $itemKey = 'foo';
        $tag = $adapter->getItem('bar'.TagAwareAdapter::TAGS_PREFIX);
        $adapter->save($tag->set('abcABC'));

        $item = $pool->getItem($itemKey);
        $pool->save($item->tag('bar'));

        $tag = $adapter->getItem('bar'.TagAwareAdapter::TAGS_PREFIX);
        $adapter->save($tag->set('001122'));

        $pool->invalidateTags(['bar']);
        $pool->getItem($itemKey);
    }

    #[DoesNotPerformAssertions]
    public function testToleranceForStringsAsTagVersionsCase5()
    {
        $pool = $this->createCachePool();
        $pool2 = $this->createCachePool();
        $adapter = new FilesystemAdapter();

        $itemKey1 = 'foo';
        $item = $pool->getItem($itemKey1);
        $pool->save($item->tag('bar'));

        $tag = $adapter->getItem('bar'.TagAwareAdapter::TAGS_PREFIX);
        $adapter->save($tag->set('abcABC'));

        $itemKey2 = 'baz';
        $item = $pool2->getItem($itemKey2);
        $pool2->save($item->tag('bar'));
        foreach ($pool->getItems([$itemKey1, $itemKey2]) as $item) {
            // run generator
        }
    }

    public function testResetClearsInternalStateEvenOnCommitFailure()
    {
        $pool = new class extends ArrayAdapter {
            public function commit(): bool
            {
                return false;
            }
        };

        $adapter = new TagAwareAdapter($pool);
        $item = $adapter->getItem('foo');
        $item->set('bar');
        $adapter->saveDeferred($item);

        // Simulate some known tag versions
        $propertyTags = new \ReflectionProperty($adapter, 'knownTagVersions');
        $propertyTags->setValue($adapter, ['tag1' => 1]);

        $adapter->reset();

        $propertyDeferred = new \ReflectionProperty($adapter, 'deferred');
        $this->assertEmpty($propertyDeferred->getValue($adapter), 'The deferred items must be cleared even if commit fails.');
        $this->assertEmpty($propertyTags->getValue($adapter), 'The known tag versions must be cleared even if commit fails.');
    }

    public function testResetForwardsTheShutdownFlagToShutdownAwarePoolsOnly()
    {
        $pool = new TagAwareAdapter($items = new ShutdownAwareAdapter(), $tags = new ResettableAdapter());
        $pool->reset();
        $pool->reset(true);

        $this->assertSame([false, true], $items->resets);
        $this->assertSame([[], []], $tags->resets);
    }
}
