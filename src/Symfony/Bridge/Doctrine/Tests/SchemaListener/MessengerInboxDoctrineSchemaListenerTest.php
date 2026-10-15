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
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\SchemaListener\MessengerInboxDoctrineSchemaListener;
use Symfony\Component\Messenger\Bridge\Doctrine\Inbox\DoctrineInbox;

#[RequiresPhpExtension('pdo_sqlite')]
class MessengerInboxDoctrineSchemaListenerTest extends TestCase
{
    public function testPostGenerateSchema()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $event = new GenerateSchemaEventArgs($entityManager, new Schema());

        $listener = new MessengerInboxDoctrineSchemaListener([new DoctrineInbox($connection, 'orders_inbox'), new DoctrineInbox($connection, 'invoices_inbox')]);
        $listener->postGenerateSchema($event);

        $this->assertTrue($event->getSchema()->hasTable('orders_inbox'));
        $this->assertTrue($event->getSchema()->hasTable('invoices_inbox'));
    }

    public function testPostGenerateSchemaRespectsSchemaFilter()
    {
        $configuration = new Configuration();
        $configuration->setSchemaAssetsFilter(static fn (string $tableName) => 'messenger_inbox' !== $tableName);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $event = new GenerateSchemaEventArgs($entityManager, new Schema());

        $listener = new MessengerInboxDoctrineSchemaListener([new DoctrineInbox($connection)]);
        $listener->postGenerateSchema($event);

        $this->assertFalse($event->getSchema()->hasTable('messenger_inbox'));
    }
}
