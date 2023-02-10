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
use Symfony\Component\Lock\Store\MongoDbStore;
use Symfony\Component\Lock\Store\StoreFactory;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @requires extension mongo
 */
class MongoDbStoreFactoryTest extends TestCase
{
    public function testCreateMongoDbCollectionStore()
    {
        $store = StoreFactory::createStore($this->createMock(\MongoDB\Collection::class));

        $this->assertInstanceOf(MongoDbStore::class, $store);
    }

    public function testCreateMongoDbCollectionStoreAsDsn()
    {
        $store = StoreFactory::createStore('mongodb://localhost/test?collection=lock');

        $this->assertInstanceOf(MongoDbStore::class, $store);
    }
}
