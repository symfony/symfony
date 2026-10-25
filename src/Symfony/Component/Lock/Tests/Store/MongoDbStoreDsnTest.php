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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Store\MongoDbStore;

class MongoDbStoreDsnTest extends TestCase
{
    public function testDatabasePathIsDecoded()
    {
        $store = new MongoDbStore('mongodb://localhost/%61pp?authSource=admin&collection=locks');
        $reflection = new \ReflectionObject($store);

        $this->assertSame('app.locks', $reflection->getProperty('namespace')->getValue($store));
        $this->assertSame('mongodb://localhost/%61pp?authSource=admin', $reflection->getProperty('uri')->getValue($store));

        $store = new MongoDbStore('mongodb://localhost/%2561pp?collection=locks');
        $this->assertSame('%61pp.locks', $reflection->getProperty('namespace')->getValue($store));
        $this->assertSame('mongodb://localhost/%2561pp', $reflection->getProperty('uri')->getValue($store));
    }

    public function testZeroDatabasePath()
    {
        $store = new MongoDbStore('mongodb://localhost/0?collection=locks');
        $reflection = new \ReflectionObject($store);

        $this->assertSame('0.locks', $reflection->getProperty('namespace')->getValue($store));
        $this->assertSame('mongodb://localhost/0', $reflection->getProperty('uri')->getValue($store));
    }

    public function testDatabasePathOverridesOptions()
    {
        foreach (['0' => '0', '%61pp' => 'app', 'path_db' => 'path_db'] as $path => $database) {
            $store = new MongoDbStore('mongodb://localhost/'.$path.'?collection=locks', ['database' => 'fallback', 'collection' => 'other']);

            $this->assertSame($database.'.locks', (new \ReflectionObject($store))->getProperty('namespace')->getValue($store));
        }
    }

    public function testEmptyDatabasePathUsesOptions()
    {
        foreach (['mongodb://localhost/?collection=locks', 'mongodb://localhost?collection=locks'] as $uri) {
            $store = new MongoDbStore($uri, ['database' => 'fallback']);

            $this->assertSame('fallback.locks', (new \ReflectionObject($store))->getProperty('namespace')->getValue($store));
        }
    }

    public function testHostLists()
    {
        foreach ([
            'mongodb://h1:27017,h2/db?collection=locks' => 'mongodb://h1:27017,h2/db',
            'mongodb://h1,h2:27018/db?replicaSet=rs&collection=locks' => 'mongodb://h1,h2:27018/db?replicaSet=rs',
            'mongodb://user:p%40ss@h1:27017,h2/db?collection=locks&authSource=admin' => 'mongodb://user:p%40ss@h1:27017,h2/db?authSource=admin',
            'mongodb://[::1]:27017,[::1]/db?collection=locks' => 'mongodb://[::1]:27017,[::1]/db',
        ] as $uri => $expectedUri) {
            $store = new MongoDbStore($uri);
            $reflection = new \ReflectionObject($store);

            $this->assertSame('db.locks', $reflection->getProperty('namespace')->getValue($store));
            $this->assertSame($expectedUri, $reflection->getProperty('uri')->getValue($store));
        }
    }

    public function testMissingHostIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is invalid.');

        new MongoDbStore('mongodb:///db?collection=locks');
    }

    public function testMissingDatabaseRequiresAnOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires the "database" in the URI path or option');

        new MongoDbStore('mongodb://localhost/?collection=locks');
    }
}
