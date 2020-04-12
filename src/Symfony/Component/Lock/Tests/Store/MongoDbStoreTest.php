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
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MongoDbStore;

/**
 * @author Joe Bennett <joe@assimtech.com>
 *
 * @requires function \MongoDB\Client::__construct
 */
class MongoDbStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    public static function setupBeforeClass(): void
    {
        $client = self::getMongoClient();
        $client->listDatabases();
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
        $key = new Key(uniqid(__METHOD__, true));

        $store = new MongoDbStore($mongo, $options);

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

        yield ['mongodb://localhost/test?collection=lock', []];
        yield ['mongodb://localhost/test', ['collection' => 'lock']];
        yield ['mongodb://localhost/', ['database' => 'test', 'collection' => 'lock']];
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
        yield [$client, ['collection' => 'lock']];
        yield [$client, ['database' => 'test']];

        yield ['mongodb://localhost/?collection=lock', []];
        yield ['mongodb://localhost/test', []];
        yield ['mongodb://localhost/', []];
    }
}
