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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MongoDbStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

require_once __DIR__.'/stubs/mongodb.php';

/**
 * @author Joe Bennett <joe@assimtech.com>
 */
#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
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
        return new Manager(getenv('MONGODB_URI'));
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

    #[DataProvider('provideConstructorArgs')]
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
        yield ['mongodb://localhost', []];
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

    #[DataProvider('provideInvalidConstructorArgs')]
    public function testInvalidConstructionMethods($mongo, array $options)
    {
        $this->expectException(InvalidArgumentException::class);

        new MongoDbStore($mongo, $options);
    }

    public static function provideInvalidConstructorArgs()
    {
        yield ['redis://localhost', []];
        yield ['mongodb://', []];
        yield ['mongodb://host1:27017,host2/app', []];
        yield ['mongodb://user:p?ss@localhost/app', []];
    }

    public function testRepeatedOptionsUseTheLastValue()
    {
        $store = new MongoDbStore('mongodb://localhost/app?collection=a&collection=b');
        $reflection = new \ReflectionObject($store);

        $this->assertSame('app.b', $reflection->getProperty('namespace')->getValue($store));
        $this->assertSame('mongodb://localhost/app', $reflection->getProperty('uri')->getValue($store));
    }

    #[DataProvider('provideMultipleHosts')]
    public function testMultipleHosts(string $dsn, string $expectedUri)
    {
        $store = new MongoDbStore($dsn);
        $reflection = new \ReflectionObject($store);

        $this->assertSame('test.locks', $reflection->getProperty('namespace')->getValue($store));
        $this->assertSame($expectedUri, $reflection->getProperty('uri')->getValue($store));
    }

    public static function provideMultipleHosts(): iterable
    {
        yield 'with ports' => ['mongodb://host1:27017,host2:27017/test?collection=locks', 'mongodb://host1:27017,host2:27017/test'];
        yield 'without ports' => ['mongodb://host1,host2/test', 'mongodb://host1,host2/test'];
        yield 'with credentials' => ['mongodb://user:pass@host1:27017,host2:27017/test', 'mongodb://user:pass@host1:27017,host2:27017/test'];
    }

    public function testQuestionMarksAndDatabasePrecedence()
    {
        $store = new MongoDbStore('mongodb://localhost/app?collection=c&appName=a?b');
        $reflection = new \ReflectionObject($store);
        $this->assertSame('app.c', $reflection->getProperty('namespace')->getValue($store));
        $this->assertSame('mongodb://localhost/app?appName=a?b', $reflection->getProperty('uri')->getValue($store));

        $store = new MongoDbStore('mongodb://localhost/path_db?database=query_db');
        $this->assertSame('path_db.locks', (new \ReflectionObject($store))->getProperty('namespace')->getValue($store));
    }

    #[DataProvider('provideEmptyOptions')]
    public function testEmptyOptionsAreRejected(string $dsn, array $options)
    {
        $this->expectException(InvalidArgumentException::class);

        new MongoDbStore($dsn, $options);
    }

    public static function provideEmptyOptions(): iterable
    {
        yield 'empty collection in the DSN' => ['mongodb://localhost/app?collection=', []];
        yield 'empty database in the DSN' => ['mongodb://localhost/app?database=', []];
        yield 'collection without value in the DSN' => ['mongodb://localhost/app?collection', []];
        yield 'empty collection option' => ['mongodb://localhost', ['collection' => '']];
        yield 'empty database option' => ['mongodb://localhost', ['database' => '']];
    }

    public function testUriOverridesEmptyOptions()
    {
        $store = new MongoDbStore('mongodb://localhost/app?collection=locks', ['database' => '', 'collection' => '']);

        $this->assertSame('app.locks', (new \ReflectionObject($store))->getProperty('namespace')->getValue($store));
    }

    public function testDecodedDatabasePath()
    {
        $store = new MongoDbStore('mongodb://localhost/my%2Ddb');
        $this->assertSame('my-db.locks', (new \ReflectionObject($store))->getProperty('namespace')->getValue($store));

        $store = new MongoDbStore('mongodb://localhost/0');
        $this->assertSame('0.locks', (new \ReflectionObject($store))->getProperty('namespace')->getValue($store));
    }

    public function testBareDsnUsesDefaultDatabaseAndCollection()
    {
        $store = new MongoDbStore('mongodb://localhost');
        $reflection = new \ReflectionObject($store);

        $this->assertSame('app.locks', $reflection->getProperty('namespace')->getValue($store));

        $options = $reflection->getProperty('options')->getValue($store);
        $this->assertSame('app', $options['database']);
        $this->assertSame('locks', $options['collection']);
    }

    #[DataProvider('provideUriCollectionStripArgs')]
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
        yield ['mongodb://user:&collection=a@localhost/?collection=lock', ['database' => 'test'], 'mongodb://user:&collection=a@localhost/'];
        yield ['mongodb://localhost/app?appName=my%26app&collection=lock&w=majority', [], 'mongodb://localhost/app?appName=my%26app&w=majority'];
        yield ['mongodb://localhost/app?appName=a=b&collection=lock', [], 'mongodb://localhost/app?appName=a=b'];
        yield ['mongodb://localhost/app?collection=a&collection=b&w=1', [], 'mongodb://localhost/app?w=1'];
    }
}
