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

        $pool->save($item);
        $this->assertTrue($pool->getItem('foo')->isHit());

        $tagsPool->deleteItem('#baz');

        $this->assertTrue($pool->getItem('foo')->isHit());

        sleep(5);

        $this->assertTrue($pool->getItem('foo')->isHit());

        sleep(20);

        $this->assertFalse($pool->getItem('foo')->isHit());
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

    /**
     * @dataProvider providePackedItemValue
     */
    public function testUnpackCacheItem($packedItemValue, $isValid, $value)
    {
        $pool = $this->createCachePool();
        $itemKey = 'foo';

        $adapter = new FilesystemAdapter();
        $item = $adapter->getItem('$'.$itemKey);
        $adapter->save($item->set($packedItemValue));

        $item = $pool->getItem($itemKey);
        $this->assertSame($isValid, $item->isHit());
        $this->assertEquals($value, $item->get());

        foreach ($pool->getItems([$itemKey]) as $item) {
            $this->assertSame($isValid, $item->isHit());
            $this->assertEquals($value, $item->get());
        }
    }

    public function providePackedItemValue()
    {
        return [
            // missed fields
            [[], false, null],
            [['$' => ''], false, null],
            [['#' => []], false, null],
            [['$' => '', '^' => ''], false, null],
            [['#' => [], '^' => ''], false, null],
            // extra fields
            [[null, '$' => '', '#' => []], false, null],
            [['$' => '', '#' => [], '' => ''], false, null],
            // wrong order of fields
            [['#' => [], '$' => ''], false, null],
            [['$' => '$', '^' => '', '#' => []], false, null],
            [['^' => '', '$' => '', '#' => []], false, null],
            // bad types
            [null, false, null],
            [serialize(['$' => '$', '#' => []]), false, null],
            [(object) ['$' => '$', '#' => []], false, null],
            [['$' => '', '#' => ''], false, null],
            [['$' => '', '#' => null], false, null],
            [['$' => '', '#' => new \stdClass()], false, null],
            [['$' => '', '#' => [], '^' => []], false, null],
            [['$' => '', '#' => [], '^' => null], false, null],
            [['$' => '', '#' => [], '^' => new \stdClass()], false, null],
            // good items
            [['$' => 0, '#' => []], true, 0],
            [['$' => '0', '#' => []], true, '0'],
            [['$' => [''], '#' => []], true, ['']],
            [['$' => (object) ['$' => '$'], '#' => []], true, (object) ['$' => '$']],
            [['$' => null, '#' => []], true, null],
            [['$' => null, '#' => [], '^' => ''], true, null],
            [['$' => '1', '#' => [], '^' => ''], true, '1'],
            [['$' => [[0]], '#' => [], '^' => ''], true, [[0]]],
            [['$' => serialize((object) ['$' => '$']), '#' => [], '^' => ''], true, serialize((object) ['$' => '$'])],
        ];
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
}
