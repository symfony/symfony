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
use Symfony\Bridge\Doctrine\SchemaListener\MessengerTransportDoctrineSchemaSubscriber;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerTransportDoctrineSchemaSubscriberTest extends TestCase
{
    public function testPostGenerateSchema()
    {
        $schema = new Schema();
        $dbalConnection = self::createMock(Connection::class);
        $entityManager = self::createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getConnection')
            ->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = self::createMock(DoctrineTransport::class);
        $doctrineTransport->expects(self::once())
            ->method('configureSchema')
            ->with($schema, $dbalConnection);
        $otherTransport = self::createMock(TransportInterface::class);
        $otherTransport->expects(self::never())
            ->method(self::anything());

        $subscriber = new MessengerTransportDoctrineSchemaSubscriber([$doctrineTransport, $otherTransport]);
        $subscriber->postGenerateSchema($event);
    }

    public function testOnSchemaCreateTable()
    {
        $platform = self::createMock(AbstractPlatform::class);
        $table = new Table('queue_table');
        $event = new SchemaCreateTableEventArgs($table, [], [], $platform);

        $otherTransport = self::createMock(TransportInterface::class);
        $otherTransport->expects(self::never())
            ->method(self::anything());

        $doctrineTransport = self::createMock(DoctrineTransport::class);
        $doctrineTransport->expects(self::once())
            ->method('getExtraSetupSqlForTable')
            ->with($table)
            ->willReturn(['ALTER TABLE pizza ADD COLUMN extra_cheese boolean']);

        // we use the platform to generate the full create table sql
        $platform->expects(self::once())
            ->method('getCreateTableSQL')
            ->with($table)
            ->willReturn('CREATE TABLE pizza (id integer NOT NULL)');

        $subscriber = new MessengerTransportDoctrineSchemaSubscriber([$otherTransport, $doctrineTransport]);
        $subscriber->onSchemaCreateTable($event);
        self::assertTrue($event->isDefaultPrevented());
        self::assertSame([
            'CREATE TABLE pizza (id integer NOT NULL)',
            'ALTER TABLE pizza ADD COLUMN extra_cheese boolean',
        ], $event->getSql());
    }

    public function testOnSchemaCreateTableNoExtraSql()
    {
        $platform = self::createMock(AbstractPlatform::class);
        $table = new Table('queue_table');
        $event = new SchemaCreateTableEventArgs($table, [], [], $platform);

        $doctrineTransport = self::createMock(DoctrineTransport::class);
        $doctrineTransport->expects(self::once())
            ->method('getExtraSetupSqlForTable')
            ->willReturn([]);

        $platform->expects(self::never())
            ->method('getCreateTableSQL');

        $subscriber = new MessengerTransportDoctrineSchemaSubscriber([$doctrineTransport]);
        $subscriber->onSchemaCreateTable($event);
        self::assertFalse($event->isDefaultPrevented());
    }
}
