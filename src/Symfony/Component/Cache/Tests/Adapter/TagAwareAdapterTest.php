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

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Tests\Fixtures\PrunableAdapter;

/**
 * @group time-sensitive
 */
class TagAwareAdapterTest extends AdapterTestCase
{
    use TagAwareTestTrait;

    public function createCachePool($defaultLifetime = 0): CacheItemPoolInterface
    {
        return new TagAwareAdapter(new FilesystemAdapter('', $defaultLifetime));
    }

    public static function tearDownAfterClass(): void
    {
        FilesystemAdapterTest::rmdir(sys_get_temp_dir().'/symfony-cache');
    }

    /**
     * Test feature specific to TagAwareAdapter as it implicit needs to save deferred when also saving expiry info.
     */
    public function testInvalidateCommitsSeperatePools()
    {
        $pool1 = $this->createCachePool();

        $foo = $pool1->getItem('foo');
        $foo->tag('tag');

        $pool1->saveDeferred($foo->set('foo'));
        $pool1->invalidateTags(['tag']);

        $pool2 = $this->createCachePool();
        $foo = $pool2->getItem('foo');

        $this->assertTrue($foo->isHit());
    }

    public function testPrune()
    {
        $cache = new TagAwareAdapter($this->getPruneableMock());
        $this->assertTrue($cache->prune());

        $cache = new TagAwareAdapter($this->getNonPruneableMock());
        $this->assertFalse($cache->prune());

        $cache = new TagAwareAdapter($this->getFailingPruneableMock());
        $this->assertFalse($cache->prune());
    }

    public function testKnownTagVersionsTtl()
    {
        $itemsPool = new FilesystemAdapter('', 10);
        $tagsPool = $this->createMock(AdapterInterface::class);

        $pool = new TagAwareAdapter($itemsPool, $tagsPool, 10);

        $item = $pool->getItem('foo');
        $item->tag(['baz']);
        $item->expiresAfter(100);

        $tag = $this->createMock(CacheItemInterface::class);
        $tag->expects(self::exactly(2))->method('get')->willReturn(10);

        $tagsPool->expects(self::exactly(2))->method('getItems')->willReturn([
            'baz'.TagAwareAdapter::TAGS_PREFIX => $tag,
        ]);

        $pool->save($item);
        $this->assertTrue($pool->getItem('foo')->isHit());
        $this->assertTrue($pool->getItem('foo')->isHit());

        sleep(20);

        $this->assertTrue($pool->getItem('foo')->isHit());

        sleep(5);

        $this->assertTrue($pool->getItem('foo')->isHit());
    }

    public function testTagEntryIsCreatedForItemWithoutTags()
    {
        $pool = $this->createCachePool();

        $itemKey = 'foo';
        $item = $pool->getItem($itemKey);
        $pool->save($item);

        $adapter = new FilesystemAdapter();
        $this->assertTrue($adapter->hasItem(TagAwareAdapter::TAGS_PREFIX.$itemKey));
    }

    public function testHasItemReturnsFalseWhenPoolDoesNotHaveItemTags()
    {
        $pool = $this->createCachePool();

        $itemKey = 'foo';
        $item = $pool->getItem($itemKey);
        $pool->save($item);

        $anotherPool = $this->createCachePool();

        $adapter = new FilesystemAdapter();
        $adapter->deleteItem(TagAwareAdapter::TAGS_PREFIX.$itemKey); // simulate item losing tags pair

        $this->assertFalse($anotherPool->hasItem($itemKey));
    }

    public function testGetItemReturnsCacheMissWhenPoolDoesNotHaveItemTags()
    {
        $pool = $this->createCachePool();

        $itemKey = 'foo';
        $item = $pool->getItem($itemKey);
        $pool->save($item);

        $anotherPool = $this->createCachePool();

        $adapter = new FilesystemAdapter();
        $adapter->deleteItem(TagAwareAdapter::TAGS_PREFIX.$itemKey); // simulate item losing tags pair

        $item = $anotherPool->getItem($itemKey);
        $this->assertFalse($item->isHit());
    }

    public function testHasItemReturnsFalseWhenPoolDoesNotHaveItemAndOnlyHasTags()
    {
        $pool = $this->createCachePool();

        $itemKey = 'foo';
        $item = $pool->getItem($itemKey);
        $pool->save($item);

        $anotherPool = $this->createCachePool();

        $adapter = new FilesystemAdapter();
        $adapter->deleteItem($itemKey); // simulate losing item but keeping tags

        $this->assertFalse($anotherPool->hasItem($itemKey));
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

    public function testGetItemReturnsCacheMissWhenPoolDoesNotHaveItemAndOnlyHasTags()
    {
        $pool = $this->createCachePool();

        $itemKey = 'foo';
        $item = $pool->getItem($itemKey);
        $pool->save($item);

        $anotherPool = $this->createCachePool();

        $adapter = new FilesystemAdapter();
        $adapter->deleteItem($itemKey); // simulate losing item but keeping tags

        $item = $anotherPool->getItem($itemKey);
        $this->assertFalse($item->isHit());
    }

    /**
     * @return PruneableInterface&MockObject
     */
    private function getPruneableMock(): PruneableInterface
    {
        $pruneable = $this->createMock(PrunableAdapter::class);

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(true);

        return $pruneable;
    }

    /**
     * @return PruneableInterface&MockObject
     */
    private function getFailingPruneableMock(): PruneableInterface
    {
        $pruneable = $this->createMock(PrunableAdapter::class);

        $pruneable
            ->expects($this->atLeastOnce())
            ->method('prune')
            ->willReturn(false);

        return $pruneable;
    }

    /**
     * @return AdapterInterface&MockObject
     */
    private function getNonPruneableMock(): AdapterInterface
    {
        return $this->createMock(AdapterInterface::class);
    }

    /**
     * @doesNotPerformAssertions
     */
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

    /**
     * @doesNotPerformAssertions
     */
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

    /**
     * @doesNotPerformAssertions
     */
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

    /**
     * @doesNotPerformAssertions
     */
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

    /**
     * @doesNotPerformAssertions
     */
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
}
