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
use Symfony\Bridge\Doctrine\SchemaListener\LockStoreSchemaListener;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

class LockStoreSchemaListenerTest extends TestCase
{
    public function testPostGenerateSchemaLockPdo()
    {
        $schema = new Schema();
        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn(new Configuration());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getConnection')
            ->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $lockStore = $this->createMock(DoctrineDbalStore::class);
        $lockStore->expects($this->once())
            ->method('configureSchema')
            ->with($schema, $this->callback(static fn () => true));

        $subscriber = new LockStoreSchemaListener((static fn () => yield $lockStore)());
        $subscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchemaRespectsSchemaFilter()
    {
        $schema = new Schema();

        $configuration = new Configuration();
        $configuration->setSchemaAssetsFilter(static fn (string $tableName) => 'lock_keys' !== $tableName);

        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn($configuration);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $lockStore = $this->createStub(DoctrineDbalStore::class);
        $lockStore->method('configureSchema')
            ->willReturnCallback(static function (Schema $schema) {
                $table = $schema->createTable('lock_keys');
                $table->addColumn('key_id', 'string');
            });

        $listener = new LockStoreSchemaListener([$lockStore]);
        $listener->postGenerateSchema($event);

        $this->assertFalse($schema->hasTable('lock_keys'));
    }
}
