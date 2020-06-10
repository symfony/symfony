<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use MongoDB\Client;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MongoDbStore;

/**
 * @author Joe Bennett <joe@assimtech.com>
 *
 * @requires extension mongodb
 * @group integration
 */
class MongoDbStoreTest extends AbstractStoreTest
{
    use ExpectDeprecationTrait;
    use ExpiringStoreTestTrait;

    public static function setupBeforeClass(): void
    {
        if (!class_exists(\MongoDB\Client::class)) {
            self::markTestSkipped('The mongodb/mongodb package is required.');
        }

        $client = self::getMongoClient();
        try {
            $client->listDatabases();
        } catch (ConnectionTimeoutException $e) {
            self::markTestSkipped('MongoDB server not found.');
        }
    }

    private static function getMongoClient(): Client
    {
        return new Client('mongodb://'.getenv('MONGODB_HOST'));
    }

    protected function getClockDelay(): int
    {
        return 250000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore(): PersistingStoreInterface
    {
        return new MongoDbStore(self::getMongoClient(), [
            'database' => 'test',
            'collection' => 'lock',
        ]);
    }

    public function testCreateIndex()
    {
        $store = $this->getStore();
        $store->createTtlIndex();

        $client = self::getMongoClient();
        $collection = $client->selectCollection(
            'test',
            'lock'
        );
        $indexes = [];
        foreach ($collection->listIndexes() as $index) {
            $indexes[] = $index->getName();
        }
        $this->assertContains('expires_at_1', $indexes);
    }

    public function testNonBlocking()
    {
        $this->expectException(NotSupportedException::class);

        $store = $this->getStore();

        $key = new Key(uniqid(__METHOD__, true));

        $store->waitAndSave($key);
    }

    /**
     * @dataProvider provideConstructorArgs
     */
    public function testConstructionMethods($mongo, array $options)
    {
        $store = new MongoDbStore($mongo, $options);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function provideConstructorArgs()
    {
        $client = self::getMongoClient();
        yield [$client, ['database' => 'test', 'collection' => 'lock']];

        $collection = $client->selectCollection('test', 'lock');
        yield [$collection, []];

        yield ['mongodb://localhost/', ['database' => 'test', 'collection' => 'lock']];
        yield ['mongodb://localhost/test', ['database' => 'test', 'collection' => 'lock']];
    }

    /**
     * @dataProvider provideDeprecatedDatabaseConstructorArgs
     * @group legacy
     */
    public function testDeprecatedDatabaseConstructionMethods($mongo, array $options)
    {
        $this->expectDeprecation('Since symfony/lock 5.2: Constructing a "Symfony\Component\Lock\Store\MongoDbStore" by passing the "database" via a connection URI is deprecated. Either contruct with a "MongoDB\Collection" or pass it via $options instead.');

        $store = new MongoDbStore($mongo, $options);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function provideDeprecatedDatabaseConstructorArgs()
    {
        yield ['mongodb://localhost/test', ['collection' => 'lock']];
    }

    /**
     * @dataProvider provideDeprecatedCollectionConstructorArgs
     * @group legacy
     */
    public function testDeprecatedCollectionConstructionMethods($mongo, array $options)
    {
        $this->expectDeprecation('Since symfony/lock 5.2: Constructing a "Symfony\Component\Lock\Store\MongoDbStore" by passing the "collection" via a connection URI is deprecated. Either contruct with a "MongoDB\Collection" or pass it via $options instead.');

        $store = new MongoDbStore($mongo, $options);

        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function provideDeprecatedCollectionConstructorArgs()
    {
        yield ['mongodb://localhost/?collection=lock', ['database' => 'test', 'collection' => 'lock']];
        yield ['mongodb://localhost/?collection=lock', ['database' => 'test']];
    }

    /**
     * @dataProvider provideInvalidConstructorArgs
     */
    public function testInvalidConstructionMethods($mongo, array $options)
    {
        $this->expectException(InvalidArgumentException::class);

        new MongoDbStore($mongo, $options);
    }

    public function provideInvalidConstructorArgs()
    {
        $client = self::getMongoClient();
        yield [$client, []];
        yield [$client, ['database' => 'test']];
        yield [$client, ['collection' => 'lock']];

        yield ['mongodb://localhost/?collection=lock', ['collection' => 'lock']];
        yield ['mongodb://localhost/?collection=lock', []];
        yield ['mongodb://localhost/', ['collection' => 'lock']];
        yield ['mongodb://localhost/', ['database' => 'test']];
        yield ['mongodb://localhost/', []];
        yield ['mongodb://localhost/test', ['database' => 'test']];
        yield ['mongodb://localhost/test', []];
    }
}
