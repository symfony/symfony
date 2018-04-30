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

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @group time-sensitive
 */
class TagAwareAdapterTest extends AdapterTestCase
{
    public function createCachePool($defaultLifetime = 0)
    {
        return new TagAwareAdapter(new FilesystemAdapter('', $defaultLifetime));
    }

    public static function tearDownAfterClass()
    {
        FilesystemAdapterTest::rmdir(sys_get_temp_dir().'/symfony-cache');
    }

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

        $pool->invalidateTags(array('bar'));

        $this->assertFalse($pool->getItem('i0')->isHit());
        $this->assertTrue($pool->getItem('i1')->isHit());
        $this->assertFalse($pool->getItem('i2')->isHit());
        $this->assertTrue($pool->getItem('i3')->isHit());
        $this->assertTrue($pool->getItem('foo')->isHit());

        $pool->invalidateTags(array('foo'));

        $this->assertFalse($pool->getItem('i1')->isHit());
        $this->assertFalse($pool->getItem('i3')->isHit());
        $this->assertTrue($pool->getItem('foo')->isHit());
    }

    public function testInvalidateCommits()
    {
        $pool1 = $this->createCachePool();

        $foo = $pool1->getItem('foo');
        $foo->tag('tag');

        $pool1->saveDeferred($foo->set('foo'));
        $pool1->invalidateTags(array('tag'));

        $pool2 = $this->createCachePool();
        $foo = $pool2->getItem('foo');

        $this->assertTrue($foo->isHit());
    }

    public function testTagsAreCleanedOnSave()
    {
        $pool = $this->createCachePool();

        $i = $pool->getItem('k');
        $pool->save($i->tag('foo'));

        $i = $pool->getItem('k');
        $pool->save($i->tag('bar'));

        $pool->invalidateTags(array('foo'));
        $this->assertTrue($pool->getItem('k')->isHit());
    }

    public function testTagsAreCleanedOnDelete()
    {
        $pool = $this->createCachePool();

        $i = $pool->getItem('k');
        $pool->save($i->tag('foo'));
        $pool->deleteItem('k');

        $pool->save($pool->getItem('k'));
        $pool->invalidateTags(array('foo'));

        $this->assertTrue($pool->getItem('k')->isHit());
    }

    public function testTagItemExpiry()
    {
        $pool = $this->createCachePool(10);

        $item = $pool->getItem('foo');
        $item->tag(array('baz'));
        $item->expiresAfter(100);

        $pool->save($item);
        $pool->invalidateTags(array('baz'));
        $this->assertFalse($pool->getItem('foo')->isHit());

        sleep(20);

        $this->assertFalse($pool->getItem('foo')->isHit());
    }

    public function testGetPreviousTags()
    {
        $pool = $this->createCachePool();

        $i = $pool->getItem('k');
        $pool->save($i->tag('foo'));

        $i = $pool->getItem('k');
        $this->assertSame(array('foo' => 'foo'), $i->getPreviousTags());
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
