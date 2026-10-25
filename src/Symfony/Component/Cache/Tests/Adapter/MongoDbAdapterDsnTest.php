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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\MongoDbAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

require_once __DIR__.'/stubs/mongodb.php';

/**
 * DSN parsing and constructor validation, run without a MongoDB server.
 */
class MongoDbAdapterDsnTest extends TestCase
{
    #[DataProvider('provideValidDsn')]
    public function testParseDsn(string $dsn, array $options, string $expectedDatabase, string $expectedCollection, string $expectedNamespace, string $expectedUri)
    {
        [$parsedOptions, $uri] = (new \ReflectionMethod(MongoDbAdapter::class, 'parseDsn'))->invoke(null, $dsn, $options);

        $this->assertSame($expectedDatabase, $parsedOptions['database']);
        $this->assertSame($expectedCollection, $parsedOptions['collection']);
        $this->assertSame($expectedNamespace, $parsedOptions['namespace']);
        $this->assertSame($expectedUri, $uri);
    }

    public static function provideValidDsn(): iterable
    {
        yield 'database from path, collection from query' => [
            'mongodb://localhost/mydb?collection=cache',
            [],
            'mydb',
            'cache',
            '',
            'mongodb://localhost/mydb',
        ];

        yield 'default collection when only the database is given' => [
            'mongodb://localhost/mydb',
            [],
            'mydb',
            'cache_items',
            '',
            'mongodb://localhost/mydb',
        ];

        foreach (['%61pp' => 'app', '%2561pp' => '%61pp', '0' => '0', '%30' => '0'] as $path => $database) {
            yield 'database path '.$path => [
                'mongodb://localhost/'.$path,
                [],
                $database,
                'cache_items',
                '',
                'mongodb://localhost/'.$path,
            ];
        }

        yield 'default database and collection when the DSN carries neither' => [
            'mongodb://localhost',
            [],
            'app',
            'cache_items',
            '',
            'mongodb://localhost',
        ];

        yield 'multiple hosts with ports' => [
            'mongodb://host1:27017,host2:27017/app?retryWrites=true&collection=cache',
            [],
            'app',
            'cache',
            '',
            'mongodb://host1:27017,host2:27017/app?retryWrites=true',
        ];

        yield 'multiple hosts without ports' => [
            'mongodb://host1,host2/app?collection=cache',
            [],
            'app',
            'cache',
            '',
            'mongodb://host1,host2/app',
        ];

        yield 'multiple hosts with credentials and URI options' => [
            'mongodb://user:pass@host1:27017,host2:27017/app?retryWrites=true&collection=cache',
            [],
            'app',
            'cache',
            '',
            'mongodb://user:pass@host1:27017,host2:27017/app?retryWrites=true',
        ];

        yield 'percent-encoded database in the path' => [
            'mongodb://localhost/my%2Ddb',
            [],
            'my-db',
            'cache_items',
            '',
            'mongodb://localhost/my%2Ddb',
        ];

        yield 'database named zero' => [
            'mongodb://localhost/0',
            [],
            '0',
            'cache_items',
            '',
            'mongodb://localhost/0',
        ];

        yield 'question mark in a value' => [
            'mongodb://localhost/app?collection=cache&appName=a?b',
            [],
            'app',
            'cache',
            '',
            'mongodb://localhost/app?appName=a?b',
        ];

        yield 'database from the path takes precedence over the query' => [
            'mongodb://localhost/path_db?database=query_db&collection=cache',
            [],
            'path_db',
            'cache',
            '',
            'mongodb://localhost/path_db',
        ];

        yield 'fragment is kept out of the options and in the URI' => [
            'mongodb://localhost/app?collection=cache#frag',
            [],
            'app',
            'cache',
            '',
            'mongodb://localhost/app#frag',
        ];

        yield 'namespace read from the query' => [
            'mongodb://user:pass@host:27017/db_name?collection=cache&namespace=thecacheprefix',
            [],
            'db_name',
            'cache',
            'thecacheprefix',
            'mongodb://user:pass@host:27017/db_name',
        ];

        yield 'other query parameters are kept for the driver' => [
            'mongodb://localhost/mydb?collection=cache&replicaSet=rs0',
            [],
            'mydb',
            'cache',
            '',
            'mongodb://localhost/mydb?replicaSet=rs0',
        ];

        yield 'adapter options stripped from the middle of the query' => [
            'mongodb://localhost/mydb?replicaSet=rs0&collection=cache&namespace=p&readPreference=secondaryPreferred',
            [],
            'mydb',
            'cache',
            'p',
            'mongodb://localhost/mydb?replicaSet=rs0&readPreference=secondaryPreferred',
        ];

        yield 'options take precedence over the DSN' => [
            'mongodb://localhost/mydb?collection=cache',
            ['database' => 'from_option', 'collection' => 'from_option'],
            'from_option',
            'from_option',
            '',
            'mongodb://localhost/mydb',
        ];

        yield 'null options fall back to the DSN' => [
            'mongodb://localhost/mydb?collection=cache&namespace=prefix',
            ['database' => null, 'collection' => null, 'namespace' => null],
            'mydb',
            'cache',
            'prefix',
            'mongodb://localhost/mydb',
        ];

        yield 'null options fall back to defaults' => [
            'mongodb://localhost',
            ['database' => null, 'collection' => null, 'namespace' => null],
            'app',
            'cache_items',
            '',
            'mongodb://localhost',
        ];

        yield 'mongodb+srv scheme' => [
            'mongodb+srv://localhost/mydb?collection=cache',
            [],
            'mydb',
            'cache',
            '',
            'mongodb+srv://localhost/mydb',
        ];

        yield 'Symfony options are removed from the middle' => [
            'mongodb://localhost/app?retryWrites=true&namespace=ns&w=majority',
            [],
            'app',
            'cache_items',
            'ns',
            'mongodb://localhost/app?retryWrites=true&w=majority',
        ];

        yield 'all Symfony options are removed' => [
            'mongodb://localhost/app?collection=cache&namespace=ns',
            [],
            'app',
            'cache',
            'ns',
            'mongodb://localhost/app',
        ];

        yield 'repeated Symfony options are all removed' => [
            'mongodb://localhost/app?namespace=a&namespace=b&w=1',
            [],
            'app',
            'cache_items',
            'b',
            'mongodb://localhost/app?w=1',
        ];

        yield 'repeated URI options are kept in order' => [
            'mongodb://localhost/app?readPreferenceTags=dc:ny&collection=cache&readPreferenceTags=dc:sf',
            [],
            'app',
            'cache',
            '',
            'mongodb://localhost/app?readPreferenceTags=dc:ny&readPreferenceTags=dc:sf',
        ];

        yield 'encoded ampersand in a value is kept' => [
            'mongodb://localhost/app?appName=my%26app&collection=cache&w=majority',
            [],
            'app',
            'cache',
            '',
            'mongodb://localhost/app?appName=my%26app&w=majority',
        ];

        yield 'equals sign in a value is kept' => [
            'mongodb://localhost/app?appName=a=b&collection=cache',
            [],
            'app',
            'cache',
            '',
            'mongodb://localhost/app?appName=a=b',
        ];
    }

    #[DataProvider('provideEmptyOptions')]
    public function testEmptyDatabaseOrCollectionIsRejected(string $dsn, array $options)
    {
        $this->expectException(InvalidArgumentException::class);

        (new \ReflectionMethod(MongoDbAdapter::class, 'parseDsn'))->invoke(null, $dsn, $options);
    }

    public static function provideEmptyOptions(): iterable
    {
        yield 'empty collection in the DSN' => ['mongodb://localhost/app?collection=', []];
        yield 'empty database in the DSN' => ['mongodb://localhost/app?database=', []];
        yield 'collection without value in the DSN' => ['mongodb://localhost/app?collection', []];
        yield 'empty collection option' => ['mongodb://localhost/app', ['collection' => '']];
        yield 'empty database option' => ['mongodb://localhost/app', ['database' => '']];
    }

    #[DataProvider('provideInvalidDsn')]
    public function testInvalidDsnIsRejected(string $dsn)
    {
        $this->expectException(InvalidArgumentException::class);

        (new \ReflectionMethod(MongoDbAdapter::class, 'parseDsn'))->invoke(null, $dsn, []);
    }

    public static function provideInvalidDsn(): iterable
    {
        yield 'last host without port' => ['mongodb://host1:27017,host2/app'];
        yield 'question mark in user information' => ['mongodb://user:p?ss@localhost/app'];
    }

    public function testHostListWithoutLastPortIsRejectedWithAHint()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given MongoDB DSN is invalid. When it lists several hosts, add the port of the last one too.');

        (new \ReflectionMethod(MongoDbAdapter::class, 'parseDsn'))->invoke(null, 'mongodb://host1:27017,host2/app', []);
    }

    public function testInvalidDsnWithoutHostListHasNoHint()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^The given MongoDB DSN is invalid\.$/');

        (new \ReflectionMethod(MongoDbAdapter::class, 'parseDsn'))->invoke(null, 'mongodb://user:p?ss@localhost/app?readPreferenceTags=dc:ny,rack:1', []);
    }

    public function testCreateConnectionReturnsConfiguredCollection()
    {
        if (str_contains((new \ReflectionClass(Client::class))->getFileName(), \DIRECTORY_SEPARATOR.'stubs'.\DIRECTORY_SEPARATOR)) {
            $this->markTestSkipped('The "mongodb/mongodb" package is required.');
        }

        // The client connects lazily, so no server is needed to build the collection.
        $collection = MongoDbAdapter::createConnection('mongodb://localhost/mydb?collection=cache');

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame('mydb', $collection->getDatabaseName());
        $this->assertSame('cache', $collection->getCollectionName());
        $this->assertSame(['root' => 'bson'], $collection->__debugInfo()['typeMap']);
        $this->assertNull($collection->__debugInfo()['codec']);
    }

    public function testDriverInfoIdentifiesTheAdapter()
    {
        $driverOptions = (new \ReflectionMethod(MongoDbAdapter::class, 'addDriverInfo'))->invoke(null, []);

        $this->assertSame('symfony-mongodb-cache', $driverOptions['driver']['name']);
        $this->assertNotSame('', $driverOptions['driver']['version']);
    }

    public function testDriverInfoKeepsTheOneGivenByTheApplication()
    {
        $driverOptions = (new \ReflectionMethod(MongoDbAdapter::class, 'addDriverInfo'))
            ->invoke(null, ['driver' => ['name' => 'myapp', 'version' => '1.2', 'platform' => 'my platform']]);

        $this->assertSame('symfony-mongodb-cache/myapp', $driverOptions['driver']['name']);
        $this->assertStringEndsWith('/1.2', $driverOptions['driver']['version']);
        $this->assertSame('my platform', $driverOptions['driver']['platform']);
    }

    public function testDriverInfoIsNotAccumulatedAcrossCalls()
    {
        $addDriverInfo = new \ReflectionMethod(MongoDbAdapter::class, 'addDriverInfo');
        $options = ['driver' => ['name' => 'myapp', 'version' => '1.2']];

        $this->assertSame($addDriverInfo->invoke(null, $options), $addDriverInfo->invoke(null, $options));
    }

    public function testInvalidScheme()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expecting "mongodb://" or "mongodb+srv://".');

        new MongoDbAdapter('redis://localhost');
    }

    public function testClientUsesTheDefaultCollection()
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getCollection')
            ->with('mydb', 'cache_items', $this->anything())
            ->willReturn($this->createStub(Collection::class));

        new MongoDbAdapter($client, '', 0, ['database' => 'mydb']);
    }

    public function testClientUsesTheDefaultDatabaseAndCollection()
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getCollection')
            ->with('app', 'cache_items', $this->anything())
            ->willReturn($this->createStub(Collection::class));

        new MongoDbAdapter($client);
    }
}
