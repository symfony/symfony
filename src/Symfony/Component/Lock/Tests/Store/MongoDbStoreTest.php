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
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use MongoDB\Driver\Manager;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MongoDbStore;

require_once __DIR__.'/stubs/mongodb.php';

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

    public static function setUpBeforeClass(): void
    {
        $manager = self::getMongoManager();
        try {
            $server = $manager->selectServer();
            $server->executeCommand('admin', new Command(['ping' => 1]));
        } catch (ConnectionTimeoutException $e) {
            self::markTestSkipped('MongoDB server not found.');
        }
    }

    private static function getMongoManager(): Manager
    {
        return new Manager('mongodb://'.getenv('MONGODB_HOST'));
    }

    protected function getClockDelay(): int
    {
        return 250000;
    }

    public function getStore(): PersistingStoreInterface
    {
        return new MongoDbStore(self::getMongoManager(), [
            'database' => 'test',
            'collection' => 'lock',
        ]);
    }

    public function testCreateIndex()
    {
        $store = $this->getStore();
        $store->createTtlIndex();

        $manager = self::getMongoManager();
        $result = $manager->executeReadCommand('test', new Command(['listIndexes' => 'lock']));

        $indexes = [];
        foreach ($result as $index) {
            $indexes[] = $index->name;
        }
        $this->assertContains('expires_at_1', $indexes);
    }

    /**
     * @dataProvider provideConstructorArgs
     */
    public function testConstructionMethods($mongo, array $options)
    {
        $key = new Key(__METHOD__);

        $store = new MongoDbStore($mongo, $options);

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public static function provideConstructorArgs()
    {
        yield [self::getMongoManager(), ['database' => 'test', 'collection' => 'lock']];
        yield ['mongodb://localhost/test?collection=lock', []];
        yield ['mongodb://localhost/test', ['collection' => 'lock']];
        yield ['mongodb://localhost/', ['database' => 'test', 'collection' => 'lock']];
    }

    public function testConstructWithClient()
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getManager')
            ->willReturn(self::getMongoManager());

        $this->testConstructionMethods($client, ['database' => 'test', 'collection' => 'lock']);
    }

    public function testConstructWithDatabase()
    {
        $database = $this->createMock(Database::class);
        $database->expects($this->once())
            ->method('getManager')
            ->willReturn(self::getMongoManager());
        $database->expects($this->once())
            ->method('getDatabaseName')
            ->willReturn('test');

        $this->testConstructionMethods($database, ['collection' => 'lock']);
    }

    public function testConstructWithCollection()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('getManager')
            ->willReturn(self::getMongoManager());
        $collection->expects($this->once())
            ->method('getDatabaseName')
            ->willReturn('test');
        $collection->expects($this->once())
            ->method('getCollectionName')
            ->willReturn('lock');

        $this->testConstructionMethods($collection, []);
    }

    public function testUriPrecedence()
    {
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
        $manager = self::getMongoManager();
        yield [$manager, ['collection' => 'lock']];
        yield [$manager, ['database' => 'test']];

        yield ['mongodb://localhost/?collection=lock', []];
        yield ['mongodb://localhost/test', []];
        yield ['mongodb://localhost/', []];
    }

    /**
     * @dataProvider provideUriCollectionStripArgs
     */
    public function testUriCollectionStrip(string $uri, array $options, string $driverUri)
    {
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
