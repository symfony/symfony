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

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MongoDbAdapter;
use Symfony\Component\Cache\Adapter\MongoDbTagAwareAdapter;
use Symfony\Component\Cache\Traits\MongoDbTrait;

require_once __DIR__.'/stubs/mongodb.php';

#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
#[TimeSensitive]
#[TimeSensitive(AbstractAdapter::class)]
#[TimeSensitive(MongoDbTrait::class)]
class MongoDbTagAwareAdapterTest extends MongoDbAdapterTest
{
    use TagAwareTestTrait;

    protected function createAdapter(Collection|Client|Database|string $mongo, string $namespace = '', int $defaultLifetime = 0, array $options = [], ?ClockInterface $clock = null): MongoDbAdapter|MongoDbTagAwareAdapter
    {
        return new MongoDbTagAwareAdapter($mongo, $namespace, $defaultLifetime, $options, null, $clock);
    }

    public function testInvalidateSeveralTagsAtOnce()
    {
        $cache = $this->createCachePool();

        foreach (['foo' => 'tag1', 'bar' => 'tag2', 'baz' => 'tag3'] as $key => $tag) {
            $item = $cache->getItem($key);
            $cache->save($item->set($key)->tag($tag));
        }

        $this->assertTrue($cache->invalidateTags(['tag1', 'tag2']));

        $this->assertFalse($cache->getItem('foo')->isHit());
        $this->assertFalse($cache->getItem('bar')->isHit());
        $this->assertTrue($cache->getItem('baz')->isHit());
    }

    public function testDeletingATaggedItemSucceeds()
    {
        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $cache->save($item->set('bar')->tag('tag1'));

        $this->assertTrue($cache->deleteItem('foo'));
        $this->assertFalse($cache->getItem('foo')->isHit());
    }

    public function testUntaggedItemIsStoredWithoutTags()
    {
        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $cache->save($item->set('bar'));

        // no "tags" at all, not even an empty array, so the partial index does not index the item
        $this->assertArrayNotHasKey('tags', self::findOne());
    }

    public function testSavingWithoutLifetimeRemovesTheExpiration()
    {
        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $cache->save($item->set('bar')->expiresAfter(300));

        $item = $cache->getItem('foo');
        $cache->save($item->set('baz')->expiresAfter(null));

        $this->assertArrayNotHasKey('expires_at', self::findOne());
    }

    public function testSetupCreatesTagsIndex()
    {
        $adapter = $this->createCachePool();
        $adapter->setup();

        $indexes = self::listIndexes();

        $this->assertArrayHasKey('tags_1', $indexes);
        $this->assertSame('{"tags":{"$exists":true}}', json_encode($indexes['tags_1']['partialFilterExpression']));
    }
}
