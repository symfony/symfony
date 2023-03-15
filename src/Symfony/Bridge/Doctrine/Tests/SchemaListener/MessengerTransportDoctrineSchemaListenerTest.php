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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\MessengerTransportDoctrineSchemaListener;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerTransportDoctrineSchemaListenerTest extends TestCase
{
    public function testPostGenerateSchema()
    {
        $schema = new Schema();
        $dbalConnection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getConnection')
            ->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);
        $doctrineTransport->expects($this->once())
            ->method('configureSchema')
            ->with($schema, $dbalConnection);
        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects($this->never())
            ->method($this->anything());

        $subscriber = new MessengerTransportDoctrineSchemaListener([$doctrineTransport, $otherTransport]);
        $subscriber->postGenerateSchema($event);
    }

    public function testOnSchemaCreateTable()
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $table = new Table('queue_table');
        $event = new SchemaCreateTableEventArgs($table, [], [], $platform);

        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects($this->never())
            ->method($this->anything());

        $doctrineTransport = $this->createMock(DoctrineTransport::class);
        $doctrineTransport->expects($this->once())
            ->method('getExtraSetupSqlForTable')
            ->with($table)
            ->willReturn(['ALTER TABLE pizza ADD COLUMN extra_cheese boolean']);

        // we use the platform to generate the full create table sql
        $platform->expects($this->once())
            ->method('getCreateTableSQL')
            ->with($table)
            ->willReturn('CREATE TABLE pizza (id integer NOT NULL)');

        $subscriber = new MessengerTransportDoctrineSchemaListener([$otherTransport, $doctrineTransport]);

        $subscriber->onSchemaCreateTable($event);
        $this->assertTrue($event->isDefaultPrevented());
        $this->assertSame([
            'CREATE TABLE pizza (id integer NOT NULL)',
            'ALTER TABLE pizza ADD COLUMN extra_cheese boolean',
        ], $event->getSql());
    }

    public function testOnSchemaCreateTableNoExtraSql()
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $table = new Table('queue_table');
        $event = new SchemaCreateTableEventArgs($table, [], [], $platform);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);
        $doctrineTransport->expects($this->once())
            ->method('getExtraSetupSqlForTable')
            ->willReturn([]);

        $platform->expects($this->never())
            ->method('getCreateTableSQL');

        $subscriber = new MessengerTransportDoctrineSchemaListener([$doctrineTransport]);

        $subscriber->onSchemaCreateTable($event);
        $this->assertFalse($event->isDefaultPrevented());
    }
}
