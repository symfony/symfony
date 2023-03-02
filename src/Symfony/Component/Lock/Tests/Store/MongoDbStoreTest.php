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
use PHPUnit\Framework\SkippedTestSuiteError;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MongoDbStore;

/**
 * @author Joe Bennett <joe@assimtech.com>
 *
 * @requires extension mongodb
 *
 * @group integration
 */
class MongoDbStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;

    public static function setupBeforeClass(): void
    {
        if (!class_exists(\MongoDB\Client::class)) {
            throw new SkippedTestSuiteError('The mongodb/mongodb package is required.');
        }

        $client = self::getMongoClient();
        try {
            $client->listDatabases();
        } catch (ConnectionTimeoutException $e) {
            throw new SkippedTestSuiteError('MongoDB server not found.');
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

    /**
     * @dataProvider provideConstructorArgs
     */
    public function testConstructionMethods($mongo, array $options)
    {
        $key = new Key(uniqid(__METHOD__, true));

        $store = new MongoDbStore($mongo, $options);

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public static function provideConstructorArgs()
    {
        $client = self::getMongoClient();
        yield [$client, ['database' => 'test', 'collection' => 'lock']];

        $collection = $client->selectCollection('test', 'lock');
        yield [$collection, []];

        yield ['mongodb://localhost/test?collection=lock', []];
        yield ['mongodb://localhost/test', ['collection' => 'lock']];
        yield ['mongodb://localhost/', ['database' => 'test', 'collection' => 'lock']];
    }

    public function testUriPrecedence()
    {
        $client = self::getMongoClient();

        $store = new MongoDbStore('mongodb://localhost/test_uri?collection=lock_uri', [
            'database' => 'test_option',
            'collection' => 'lock_option',
        ]);
        $storeReflection = new \ReflectionObject($store);

        $optionsProperty = $storeReflection->getProperty('options');
        $options = $optionsProperty->getValue($store);

        $this->assertSame('test_uri', $options['database']);
        $this->assertSame('lock_uri', $options['collection']);
    }

    /**
     * @dataProvider provideInvalidConstructorArgs
     */
    public function testInvalidConstructionMethods($mongo, array $options)
    {
        $this->expectException(InvalidArgumentException::class);

        new MongoDbStore($mongo, $options);
    }

    public static function provideInvalidConstructorArgs()
    {
        $client = self::getMongoClient();
        yield [$client, ['collection' => 'lock']];
        yield [$client, ['database' => 'test']];

        yield ['mongodb://localhost/?collection=lock', []];
        yield ['mongodb://localhost/test', []];
        yield ['mongodb://localhost/', []];
    }

    /**
     * @dataProvider provideUriCollectionStripArgs
     */
    public function testUriCollectionStrip(string $uri, array $options, string $driverUri)
    {
        $client = self::getMongoClient();

        $store = new MongoDbStore($uri, $options);
        $storeReflection = new \ReflectionObject($store);

        $uriProperty = $storeReflection->getProperty('uri');
        $uri = $uriProperty->getValue($store);
        $this->assertSame($driverUri, $uri);
    }

    public static function provideUriCollectionStripArgs()
    {
        yield ['mongodb://localhost/?collection=lock', ['database' => 'test'], 'mongodb://localhost/'];
        yield ['mongodb://localhost/', ['database' => 'test', 'collection' => 'lock'], 'mongodb://localhost/'];
        yield ['mongodb://localhost/test?collection=lock', [], 'mongodb://localhost/test'];
        yield ['mongodb://localhost/test', ['collection' => 'lock'], 'mongodb://localhost/test'];

        yield ['mongodb://localhost/?collection=lock&replicaSet=repl', ['database' => 'test'], 'mongodb://localhost/?replicaSet=repl'];
        yield ['mongodb://localhost/?replicaSet=repl', ['database' => 'test', 'collection' => 'lock'], 'mongodb://localhost/?replicaSet=repl'];
        yield ['mongodb://localhost/test?collection=lock&replicaSet=repl', [], 'mongodb://localhost/test?replicaSet=repl'];
        yield ['mongodb://localhost/test?replicaSet=repl', ['collection' => 'lock'], 'mongodb://localhost/test?replicaSet=repl'];

        yield ['mongodb://localhost/test?readPreferenceTags=dc:foo&collection=lock&readPreferenceTags=dc:bar', [], 'mongodb://localhost/test?readPreferenceTags=dc:foo&readPreferenceTags=dc:bar'];
        yield ['mongodb://localhost?foo_collection=x&collection=lock&bar_collection=x#collection=x', ['database' => 'test'], 'mongodb://localhost?foo_collection=x&bar_collection=x#collection=x'];
        yield ['mongodb://localhost?collection=lock&foo_collection=x&bar_collection=x#collection=x', ['database' => 'test'], 'mongodb://localhost?foo_collection=x&bar_collection=x#collection=x'];
        yield ['mongodb://localhost?foo_collection=x&bar_collection=x&collection=lock#collection=x', ['database' => 'test'], 'mongodb://localhost?foo_collection=x&bar_collection=x#collection=x'];
        yield ['mongodb://user:?collection=a@localhost?collection=lock', ['database' => 'test'], 'mongodb://user:?collection=a@localhost'];
        yield ['mongodb://user:&collection=a@localhost/?collection=lock', ['database' => 'test'], 'mongodb://user:&collection=a@localhost/'];
    }
}
