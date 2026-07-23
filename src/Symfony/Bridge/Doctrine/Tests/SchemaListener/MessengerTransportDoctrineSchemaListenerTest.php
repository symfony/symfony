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
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
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
        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn(new Configuration());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getConnection')
            ->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createMock(DoctrineTransport::class);
        $doctrineTransport->expects($this->once())
            ->method('configureSchema')
            ->with($schema, $dbalConnection, $this->callback(static fn () => true));
        $otherTransport = $this->createMock(TransportInterface::class);
        $otherTransport->expects($this->never())
            ->method($this->anything());

        $subscriber = new MessengerTransportDoctrineSchemaListener([$doctrineTransport, $otherTransport]);
        $subscriber->postGenerateSchema($event);
    }

    public function testPostGenerateSchemaRespectsSchemaFilter()
    {
        $schema = new Schema();

        $configuration = new Configuration();
        $configuration->setSchemaAssetsFilter(static fn (string $tableName) => 'messenger_messages' !== $tableName);

        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn($configuration);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createStub(DoctrineTransport::class);
        $doctrineTransport->method('configureSchema')
            ->willReturnCallback(self::addMessengerMessages(...));

        $listener = new MessengerTransportDoctrineSchemaListener([$doctrineTransport]);
        $listener->postGenerateSchema($event);

        $this->assertFalse($event->getSchema()->hasTable('messenger_messages'));
    }

    public function testPostGenerateSchemaRespectsSchemaFilterIncludingSequences()
    {
        $schema = new Schema();

        $configuration = new Configuration();
        $excluded = ['messenger_messages', 'messenger_messages_seq'];
        $configuration->setSchemaAssetsFilter(static fn (string $assetName) => !\in_array($assetName, $excluded, true));

        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn($configuration);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createStub(DoctrineTransport::class);
        $doctrineTransport->method('configureSchema')
            ->willReturnCallback(self::addMessengerMessagesWithSequence(...));

        $listener = new MessengerTransportDoctrineSchemaListener([$doctrineTransport]);
        $listener->postGenerateSchema($event);

        $this->assertFalse($event->getSchema()->hasTable('messenger_messages'));
        $this->assertFalse($event->getSchema()->hasSequence('messenger_messages_seq'));
    }

    public function testPostGenerateSchemaFilterDoesNotAffectPreExistingSequences()
    {
        if (method_exists(Schema::class, 'edit')) {
            $schema = (new Schema())->edit()->addSequence(new Sequence('existing_seq'))->create();
        } else {
            $schema = new Schema();
            $schema->createSequence('existing_seq');
        }

        $configuration = new Configuration();
        $excluded = ['messenger_messages', 'messenger_messages_seq', 'existing_seq'];
        $configuration->setSchemaAssetsFilter(static fn (string $assetName) => !\in_array($assetName, $excluded, true));

        $dbalConnection = $this->createStub(Connection::class);
        $dbalConnection->method('getConfiguration')->willReturn($configuration);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($dbalConnection);
        $event = new GenerateSchemaEventArgs($entityManager, $schema);

        $doctrineTransport = $this->createStub(DoctrineTransport::class);
        $doctrineTransport->method('configureSchema')
            ->willReturnCallback(self::addMessengerMessagesWithSequence(...));

        $listener = new MessengerTransportDoctrineSchemaListener([$doctrineTransport]);
        $listener->postGenerateSchema($event);

        $this->assertFalse($event->getSchema()->hasTable('messenger_messages'));
        $this->assertFalse($event->getSchema()->hasSequence('messenger_messages_seq'));
        $this->assertTrue($event->getSchema()->hasSequence('existing_seq'));
    }

    private static function addMessengerMessages(Schema $schema): Schema
    {
        if (method_exists($schema, 'edit')) {
            return $schema->edit()->addTable(self::buildMessengerMessagesTable())->create();
        }

        $schema->createTable('messenger_messages')->addColumn('id', 'integer', ['autoincrement' => true]);

        return $schema;
    }

    private static function addMessengerMessagesWithSequence(Schema $schema): Schema
    {
        if (method_exists($schema, 'edit')) {
            return $schema->edit()->addTable(self::buildMessengerMessagesTable())->addSequence(new Sequence('messenger_messages_seq'))->create();
        }

        $schema->createTable('messenger_messages')->addColumn('id', 'integer', ['autoincrement' => true]);
        $schema->createSequence('messenger_messages_seq');

        return $schema;
    }

    private static function buildMessengerMessagesTable(): Table
    {
        return Table::editor()
            ->setUnquotedName('messenger_messages')
            ->addColumn(Column::editor()->setUnquotedName('id')->setTypeName('integer')->setAutoincrement(true)->create())
            ->create();
    }
}
