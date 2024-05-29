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

use MongoDB\Collection;
use MongoDB\Driver\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\Store\MongoDbStore;
use Symfony\Component\Lock\Store\StoreFactory;

require_once __DIR__.'/stubs/mongodb.php';

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @requires extension mongodb
 */
class MongoDbStoreFactoryTest extends TestCase
{
    public function testCreateMongoDbCollectionStore()
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('getManager')
            ->willReturn(new Manager());
        $collection->expects($this->once())
            ->method('getCollectionName')
            ->willReturn('lock');
        $collection->expects($this->once())
            ->method('getDatabaseName')
            ->willReturn('test');

        $store = StoreFactory::createStore($collection);

        $this->assertInstanceOf(MongoDbStore::class, $store);
    }

    public function testCreateMongoDbCollectionStoreAsDsn()
    {
        $store = StoreFactory::createStore('mongodb://localhost/test?collection=lock');

        $this->assertInstanceOf(MongoDbStore::class, $store);
    }
}
