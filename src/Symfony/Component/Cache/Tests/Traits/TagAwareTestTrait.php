<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Traits;

use Symfony\Component\Cache\CacheItem;

/**
 * Common assertions for TagAware adapters.
 *
 * @method \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface createCachePool() Must be implemented by TestCase
 */
trait TagAwareTestTrait
{
    /**
     * @expectedException \Psr\Cache\InvalidArgumentException
     */
    public function testInvalidTag()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('foo');
        $item->tag(':');
    }

    public function testInvalidateTags()
    {
        $pool = $this->createCachePool();

        $i0 = $pool->getItem('i0');
        $i1 = $pool->getItem('i1');
        $i2 = $pool->getItem('i2');
        $i3 = $pool->getItem('i3');
        $foo = $pool->getItem('foo');

        $pool->save($i0->tag('bar'));
        $pool->save($i1->tag('foo'));
        $pool->save($i2->tag('foo')->tag('bar'));
        $pool->save($i3->tag('foo')->tag('baz'));
        $pool->save($foo);

        $pool->invalidateTags(['bar']);

        $this->assertFalse($pool->getItem('i0')->isHit());
        $this->assertTrue($pool->getItem('i1')->isHit());
        $this->assertFalse($pool->getItem('i2')->isHit());
        $this->assertTrue($pool->getItem('i3')->isHit());
        $this->assertTrue($pool->getItem('foo')->isHit());

        $pool->invalidateTags(['foo']);

        $this->assertFalse($pool->getItem('i1')->isHit());
        $this->assertFalse($pool->getItem('i3')->isHit());
        $this->assertTrue($pool->getItem('foo')->isHit());

        $anotherPoolInstance = $this->createCachePool();

        $this->assertFalse($anotherPoolInstance->getItem('i1')->isHit());
        $this->assertFalse($anotherPoolInstance->getItem('i3')->isHit());
        $this->assertTrue($anotherPoolInstance->getItem('foo')->isHit());
    }

    public function testInvalidateCommits()
    {
        $pool = $this->createCachePool();

        $foo = $pool->getItem('foo');
        $foo->tag('tag');

        $pool->saveDeferred($foo->set('foo'));
        $pool->invalidateTags(['tag']);

        // ??: This seems to contradict a bit logic in deleteItems, where it does unset($this->deferred[$key]); on key matches

        $foo = $pool->getItem('foo');

        $this->assertTrue($foo->isHit());
    }

    public function testTagsAreCleanedOnSave()
    {
        $pool = $this->createCachePool();

        $i = $pool->getItem('k');
        $pool->save($i->tag('foo'));

        $i = $pool->getItem('k');
        $pool->save($i->tag('bar'));

        $pool->invalidateTags(['foo']);
        $this->assertTrue($pool->getItem('k')->isHit());
    }

    public function testTagsAreCleanedOnDelete()
    {
        $pool = $this->createCachePool();

        $i = $pool->getItem('k');
        $pool->save($i->tag('foo'));
        $pool->deleteItem('k');

        $pool->save($pool->getItem('k'));
        $pool->invalidateTags(['foo']);

        $this->assertTrue($pool->getItem('k')->isHit());
    }

    public function testTagItemExpiry()
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        $pool = $this->createCachePool(10);

        $item = $pool->getItem('foo');
        $item->tag(['baz']);
        $item->expiresAfter(100);

        $pool->save($item);
        $pool->invalidateTags(['baz']);
        $this->assertFalse($pool->getItem('foo')->isHit());

        sleep(20);

        $this->assertFalse($pool->getItem('foo')->isHit());
    }

    /**
     * @group legacy
     */
    public function testGetPreviousTags()
    {
        $pool = $this->createCachePool();

        $i = $pool->getItem('k');
        $pool->save($i->tag('foo'));

        $i = $pool->getItem('k');
        $this->assertSame(['foo' => 'foo'], $i->getPreviousTags());
    }

    public function testGetMetadata()
    {
        $pool = $this->createCachePool();

        $i = $pool->getItem('k');
        $pool->save($i->tag('foo'));

        $i = $pool->getItem('k');
        $this->assertSame(['foo' => 'foo'], $i->getMetadata()[CacheItem::METADATA_TAGS]);
    }
}
