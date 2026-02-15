<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\SchemaListener;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\DoctrineDbalCacheAdapterSchemaListener;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;

class DoctrineDbalCacheAdapterSchemaListenerTest extends TestCase
{
    public function testPostGenerateSchema()
    {
        $schema = new Schema();
        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn(new Configuration());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getConnection')
            ->willReturn($dbalConnection);

        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $dbalAdapter = $this->createMock(DoctrineDbalAdapter::class);
        $dbalAdapter->expects($this->once())
            ->method('configureSchema')
            ->with($schema, $dbalConnection, $this->callback(static fn () => true));

        $subscriber = new DoctrineDbalCacheAdapterSchemaListener([$dbalAdapter]);
        $subscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchemaRespectsSchemaFilter()
    {
        $schema = new Schema();

        $configuration = new Configuration();
        $configuration->setSchemaAssetsFilter(static fn (string $tableName) => 'cache_items' !== $tableName);

        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn($configuration);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $dbalAdapter = $this->createStub(DoctrineDbalAdapter::class);
        $dbalAdapter->method('configureSchema')
            ->willReturnCallback(static function (Schema $schema) {
                $table = $schema->createTable('cache_items');
                $table->addColumn('item_id', 'string');
            });

        $listener = new DoctrineDbalCacheAdapterSchemaListener([$dbalAdapter]);
        $listener->postGenerateSchema($event);

        $this->assertFalse($schema->hasTable('cache_items'));
    }
}
