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
use Symfony\Bridge\Doctrine\SchemaListener\SchedulerTransportDoctrineSchemaSubscriber;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * {@see MessengerTransportDoctrineSchemaSubscriberTest for complete introduction}
 */
final class SchedulerTransportDoctrineSchemaSubscriberTest extends TestCase
{
    public function testPostGenerateSchema(): void
    {
        $schema = new Schema();

        $connection = $this->createMock(Connection::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);

        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);
        $doctrineTransport->expects(self::once())->method('configureSchema')->with($schema, $connection);

        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects(self::never())->method(self::anything());

        $subscriber = new SchedulerTransportDoctrineSchemaSubscriber([$doctrineTransport, $otherTransport]);
        $subscriber->postGenerateSchema($event);
    }

    public function testOnSchemaCreateTable(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);

        $table = new Table('tasks_table');
        $event = new SchemaCreateTableEventArgs($table, [], [], $platform);

        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects(self::never())->method($this->anything());

        $doctrineTransport = $this->createMock(DoctrineTransport::class);

        // we use the platform to generate the full create table sql
        $platform->expects(self::once())
            ->method('getCreateTableSQL')
            ->with($table)
            ->willReturn('CREATE TABLE pizza (id integer NOT NULL)');

        $subscriber = new SchedulerTransportDoctrineSchemaSubscriber([$otherTransport, $doctrineTransport]);
        $subscriber->onSchemaCreateTable($event);

        static::assertTrue($event->isDefaultPrevented());
        static::assertSame([
            'CREATE TABLE pizza (id integer NOT NULL)',
        ], $event->getSql());
    }
}
