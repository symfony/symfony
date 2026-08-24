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

use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Bridge\MongoDb\Adapter\MongoDbAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

require_once __DIR__.'/../Stubs/mongodb.php';

/**
 * DSN parsing and constructor validation, run without a MongoDB server.
 */
class MongoDbAdapterDsnTest extends TestCase
{
    #[DataProvider('provideValidDsn')]
    public function testParseDsn(string $dsn, array $options, string $expectedDatabase, string $expectedCollection, string $expectedNamespace, string $expectedUri)
    {
        [$parsedOptions, $uri] = (new \ReflectionMethod(MongoDbAdapter::class, 'parseDsn'))->invoke(null, $dsn, $options);

        $this->assertSame($expectedDatabase, $parsedOptions['database_name']);
        $this->assertSame($expectedCollection, $parsedOptions['collection_name']);
        $this->assertSame($expectedNamespace, $parsedOptions['namespace']);
        $this->assertSame($expectedUri, $uri);
    }

    public static function provideValidDsn(): iterable
    {
        yield 'database from path, collection from query' => [
            'mongodb://localhost/mydb?collection_name=cache',
            [],
            'mydb',
            'cache',
            '',
            'mongodb://localhost/mydb',
        ];

        yield 'namespace read from the query' => [
            'mongodb://user:pass@host:27017/db_name?collection_name=cache&namespace=thecacheprefix',
            [],
            'db_name',
            'cache',
            'thecacheprefix',
            'mongodb://user:pass@host:27017/db_name',
        ];

        yield 'other query parameters are kept for the driver' => [
            'mongodb://localhost/mydb?collection_name=cache&replicaSet=rs0',
            [],
            'mydb',
            'cache',
            '',
            'mongodb://localhost/mydb?replicaSet=rs0',
        ];

        yield 'adapter options stripped from the middle of the query' => [
            'mongodb://localhost/mydb?replicaSet=rs0&collection_name=cache&namespace=p&readPreference=secondaryPreferred',
            [],
            'mydb',
            'cache',
            'p',
            'mongodb://localhost/mydb?replicaSet=rs0&readPreference=secondaryPreferred',
        ];

        yield 'options take precedence over the DSN' => [
            'mongodb://localhost/mydb?collection_name=cache',
            ['database_name' => 'from_option', 'collection_name' => 'from_option'],
            'from_option',
            'from_option',
            '',
            'mongodb://localhost/mydb',
        ];

        yield 'mongodb+srv scheme' => [
            'mongodb+srv://localhost/mydb?collection_name=cache',
            [],
            'mydb',
            'cache',
            '',
            'mongodb+srv://localhost/mydb',
        ];
    }

    public function testCreateConnectionReturnsConfiguredCollection()
    {
        if (str_contains((new \ReflectionClass(Client::class))->getFileName(), \DIRECTORY_SEPARATOR.'Stubs'.\DIRECTORY_SEPARATOR)) {
            $this->markTestSkipped('The "mongodb/mongodb" package is required.');
        }

        // The client connects lazily, so no server is needed to build the collection.
        $collection = MongoDbAdapter::createConnection('mongodb://localhost/mydb?collection_name=cache');

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame('mydb', $collection->getDatabaseName());
        $this->assertSame('cache', $collection->getCollectionName());
        $this->assertSame(['root' => 'bson'], $collection->__debugInfo()['typeMap']);
        $this->assertNull($collection->__debugInfo()['codec']);
    }

    public function testInvalidScheme()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expecting "mongodb://" or "mongodb+srv://".');

        new MongoDbAdapter('redis://localhost');
    }

    public function testMissingDatabase()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a "database_name"');

        new MongoDbAdapter('mongodb://localhost/?collection_name=cache');
    }

    public function testMissingCollection()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a "collection_name"');

        new MongoDbAdapter('mongodb://localhost/mydb');
    }

    public function testClientWithoutDatabaseAndCollectionOptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "database_name" and "collection_name" options are required');

        new MongoDbAdapter($this->createStub(Client::class));
    }
}
