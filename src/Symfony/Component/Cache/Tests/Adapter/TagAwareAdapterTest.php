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
use Symfony\Component\Cache\Tests\Traits\TagAwareTestTrait;

/**
 * @group time-sensitive
 */
class TagAwareAdapterTest extends AdapterTestCase
{
    use TagAwareTestTrait;

    public function createCachePool($defaultLifetime = 0)
    {
        return new TagAwareAdapter(new FilesystemAdapter('', $defaultLifetime));
    }

    public static function tearDownAfterClass()
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
            ->willReturn(true);

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
            ->willReturn(false);

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
