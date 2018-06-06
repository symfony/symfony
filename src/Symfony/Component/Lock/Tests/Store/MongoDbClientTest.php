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

use Symfony\Component\Lock\Store\MongoDbStore;

/**
 * @author Joe Bennett <joe@assimtech.com>
 */
class MongoDbClientTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    public static function setupBeforeClass()
    {
        try {
            if (!class_exists(\MongoDB\Client::class)) {
                throw new \RuntimeException('The mongodb/mongodb package is required.');
            }
            $client = self::getMongoConnection();
            $client->listDatabases();
        } catch (\Exception $e) {
            self::markTestSkipped($e->getMessage());
        }
    }

    protected static function getMongoConnection(): \MongoDB\Client
    {
        return new \MongoDB\Client('mongodb://'.getenv('MONGODB_HOST'));
    }

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 250000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        return new MongoDbStore(self::getMongoConnection(), array(
            'database' => 'test',
        ));
    }

    public function testCreateIndex()
    {
        $store = $this->getStore();

        $this->assertEquals($store->createTTLIndex(), 'expires_at_1');
    }
}
