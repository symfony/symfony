<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Bridge\MongoDb\Tests\Adapter;

use MongoDB\BSON\Regex;
use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Bridge\MongoDb\Adapter\MongoDbAdapter;
use Symfony\Component\Cache\Bridge\MongoDb\Adapter\MongoDbTagAwareAdapter;
use Symfony\Component\Cache\Bridge\MongoDb\Internal\MongoDbTrait;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Test\AdapterTestCase;
use Symfony\Component\Clock\MockClock;

require_once __DIR__.'/../Stubs/mongodb.php';

#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
#[TimeSensitive(MongoDbTrait::class)]
#[TimeSensitive(AbstractAdapter::class)]
class MongoDbAdapterTest extends AdapterTestCase
{
    protected const DATABASE = 'symfony_cache_test';
    protected const COLLECTION = 'cache';

    protected static Client $client;

    public static function setUpBeforeClass(): void
    {
        if (str_contains((new \ReflectionClass(Client::class))->getFileName(), \DIRECTORY_SEPARATOR.'Stubs'.\DIRECTORY_SEPARATOR)) {
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
            self::$client->getDatabase(self::DATABASE)->drop();
        }
    }

    public function createCachePool(int $defaultLifetime = 0, ?string $testMethod = null): CacheItemPoolInterface
    {
        return new MongoDbAdapter(self::$client, str_replace('\\', '.', static::class), $defaultLifetime, [
            'database_name' => self::DATABASE,
            'collection_name' => self::COLLECTION,
        ]);
    }

    protected function isPruned(MongoDbAdapter|MongoDbTagAwareAdapter $cache, string $name): bool
    {
        $collection = (new \ReflectionProperty($cache, 'collection'))->getValue($cache);
        self::assertInstanceOf(Collection::class, $collection);

        return 0 === $collection->countDocuments(['_id' => new Regex(':'.preg_quote($name, '').'$')]);
    }

    public function testCreateConnectionFromDsnAcceptsDatabaseAndCollection()
    {
        $adapter = new MongoDbAdapter(\sprintf('%s/%s?collection_name=%s', getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', self::DATABASE, self::COLLECTION));

        $item = $adapter->getItem('foo');
        $item->set('bar');
        $this->assertTrue($adapter->save($item));
        $this->assertSame('bar', $adapter->getItem('foo')->get());
    }

    public function testExpiryUsesInjectedClock()
    {
        $clock = new MockClock();
        $adapter = new MongoDbAdapter(self::$client, str_replace('\\', '.', static::class), 0, [
            'database_name' => self::DATABASE,
            'collection_name' => self::COLLECTION,
        ], null, $clock);

        $item = $adapter->getItem('foo');
        $item->set('bar')->expiresAfter(10);
        $adapter->save($item);

        $this->assertTrue($adapter->hasItem('foo'));

        $clock->sleep(20);

        $this->assertFalse($adapter->hasItem('foo'));
        $this->assertFalse($adapter->getItem('foo')->isHit());
    }

    public function testSetupCreatesTtlIndex()
    {
        $adapter = $this->createCachePool();
        $this->assertInstanceOf(PruneableInterface::class, $adapter);
        $adapter->setup();

        $names = [];
        foreach (self::$client->getCollection(self::DATABASE, self::COLLECTION)->listIndexes() as $index) {
            $names[$index->getName()] = $index['expireAfterSeconds'] ?? null;
        }

        $this->assertArrayHasKey('expires_at_1', $names);
        $this->assertSame(0, $names['expires_at_1']);
    }
}
