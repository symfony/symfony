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

use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MongoDbAdapter;
use Symfony\Component\Cache\Adapter\MongoDbTagAwareAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Traits\MongoDbTrait;
use Symfony\Component\Clock\MockClock;

require_once __DIR__.'/stubs/mongodb.php';

#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
#[TimeSensitive]
#[TimeSensitive(AbstractAdapter::class)]
#[TimeSensitive(MongoDbTrait::class)]
class MongoDbAdapterTest extends AdapterTestCase
{
    protected const DATABASE = 'symfony_cache_test';
    protected const COLLECTION = 'cache';

    protected const OPTIONS = ['database_name' => self::DATABASE, 'collection_name' => self::COLLECTION];

    protected static Client $client;

    public static function setUpBeforeClass(): void
    {
        if (str_contains((new \ReflectionClass(Client::class))->getFileName(), \DIRECTORY_SEPARATOR.'stubs'.\DIRECTORY_SEPARATOR)) {
            self::markTestSkipped('The "mongodb/mongodb" package is required.');
        }

        self::$client = new Client(getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', ['serverSelectionTimeoutMS' => 3000]);

        try {
            self::$client->getDatabase(self::DATABASE)->command(['ping' => 1]);
        } catch (\Throwable $e) {
            self::markTestSkipped(\sprintf('MongoDB server "%s" not reachable: %s', getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', $e->getMessage()));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // start each test from a clean collection, indexes included
        self::$client->getCollection(self::DATABASE, self::COLLECTION)->drop();
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$client)) {
            self::$client->getCollection(self::DATABASE, self::COLLECTION)->drop();
        }
    }

    public function createCachePool(int $defaultLifetime = 0, ?string $testMethod = null): CacheItemPoolInterface
    {
        return $this->createAdapter(self::$client, str_replace('\\', '.', static::class), $defaultLifetime, self::OPTIONS);
    }

    protected function isPruned(MongoDbAdapter|MongoDbTagAwareAdapter $cache, string $name): bool
    {
        $collection = (new \ReflectionProperty($cache, 'collection'))->getValue($cache);
        self::assertInstanceOf(Collection::class, $collection);

        return 0 === $collection->countDocuments(['_id' => new Regex(':'.preg_quote($name, '').'$')]);
    }

    /**
     * Overridden by the tag aware test case so that the inherited tests build the right adapter.
     */
    protected function createAdapter(Collection|Client|Database|string $mongo, string $namespace = '', int $defaultLifetime = 0, array $options = [], ?ClockInterface $clock = null): MongoDbAdapter|MongoDbTagAwareAdapter
    {
        return new MongoDbAdapter($mongo, $namespace, $defaultLifetime, $options, null, $clock);
    }

    /**
     * Adds the database and the collection to the server URI, which may already
     * carry a path and query parameters such as "replicaSet".
     */
    protected static function dsn(): string
    {
        $uri = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
        [$hosts, $query] = explode('?', $uri, 2) + [1 => ''];

        return rtrim($hosts, '/').'/'.self::DATABASE.'?'.($query ? $query.'&' : '').'collection_name='.self::COLLECTION;
    }

    public function testCreateConnectionFromDsnAcceptsDatabaseAndCollection()
    {
        $adapter = $this->createAdapter(self::dsn());

        $item = $adapter->getItem('foo');
        $item->set('bar');
        $this->assertTrue($adapter->save($item));
        $this->assertSame('bar', $adapter->getItem('foo')->get());
    }

    public function testExpiryUsesInjectedClock()
    {
        $clock = new MockClock();
        $adapter = $this->createAdapter(self::$client, str_replace('\\', '.', static::class), 0, self::OPTIONS, $clock);

        $item = $adapter->getItem('foo');
        $item->set('bar')->expiresAfter(10);
        $adapter->save($item);

        $this->assertTrue($adapter->hasItem('foo'));

        $clock->sleep(20);

        $this->assertFalse($adapter->hasItem('foo'));
        $this->assertFalse($adapter->getItem('foo')->isHit());
    }

    public function testClearOnlyRemovesTheNamespacePrefix()
    {
        $cache = $this->createCachePool();
        $namespace = str_replace('\\', '.', static::class);

        $item = $cache->getItem('foo');
        $cache->save($item->set('bar'));

        // an id holding the namespace anywhere but at the start, as another pool would write
        $collection = self::$client->getCollection(self::DATABASE, self::COLLECTION);
        $collection->insertOne(['_id' => 'other.'.$namespace.':foo', 'data' => 'kept']);

        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->getItem('foo')->isHit());
        $this->assertSame(1, $collection->countDocuments(['_id' => 'other.'.$namespace.':foo']));
    }

    public function testExpirationIsStoredAsADate()
    {
        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $cache->save($item->set('bar')->expiresAfter(300));

        $document = self::findOne();

        $this->assertInstanceOf(UTCDateTime::class, $document['expires_at']);
        $this->assertEqualsWithDelta(time() + 300, $document['expires_at']->toDateTime()->getTimestamp(), 5);
    }

    public function testItemWithoutLifetimeIsStoredWithoutExpiration()
    {
        $cache = $this->createCachePool();

        $item = $cache->getItem('foo');
        $cache->save($item->set('bar'));

        // no "expires_at" at all, so the partial TTL index does not index the item
        $this->assertArrayNotHasKey('expires_at', self::findOne());
    }

    public function testCollectionReadsRawBson()
    {
        $cache = $this->createCachePool();
        $collection = (new \ReflectionProperty($cache, 'collection'))->getValue($cache);

        $this->assertSame(['root' => 'bson'], $collection->__debugInfo()['typeMap']);
        $this->assertNull($collection->__debugInfo()['codec']);
    }

    public function testNamespaceLongerThanTheMaxIdLengthIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Namespace must be');

        $this->createAdapter(self::$client, str_repeat('a', 1500), 0, self::OPTIONS);
    }

    public function testSetupCreatesTtlIndex()
    {
        $adapter = $this->createCachePool();
        $this->assertInstanceOf(PruneableInterface::class, $adapter);
        $adapter->setup();

        $indexes = self::listIndexes();

        $this->assertArrayHasKey('expires_at_1', $indexes);
        $this->assertSame(0, $indexes['expires_at_1']['expireAfterSeconds']);
        $this->assertSame('{"expires_at":{"$exists":true}}', json_encode($indexes['expires_at_1']['partialFilterExpression']));
    }

    /**
     * The single stored document, as a plain array.
     */
    protected static function findOne(): array
    {
        return self::$client->getCollection(self::DATABASE, self::COLLECTION)->findOne(
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array']]
        );
    }

    /**
     * @return array<string, \MongoDB\Model\IndexInfo>
     */
    protected static function listIndexes(): array
    {
        $indexes = [];
        foreach (self::$client->getCollection(self::DATABASE, self::COLLECTION)->listIndexes() as $index) {
            $indexes[$index->getName()] = $index;
        }

        return $indexes;
    }
}
