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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Tests\Fixtures\PrunableAdapter;
use Symfony\Component\Filesystem\Filesystem;

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
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
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

    private function getNonPruneableMock(): AdapterInterface&MockObject
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
