<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Inbox;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Inbox\DoctrineInbox;
use Symfony\Component\Messenger\Exception\LogicException;

#[RequiresPhpExtension('pdo_sqlite')]
class DoctrineInboxTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $this->connection = DriverManager::getConnection((new DsnParser())->parse(getenv('MESSENGER_DOCTRINE_DSN') ?: 'pdo-sqlite://:memory:'), $config);
        $this->connection->executeStatement('DROP TABLE IF EXISTS messenger_inbox');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS messenger_inbox');
        $this->connection->close();
    }

    public function testAMessageIsRecordedOnce()
    {
        $inbox = $this->createInbox();

        $this->connection->beginTransaction();
        $this->assertTrue($inbox->record('orders', 'id-1'));
        $this->assertFalse($inbox->record('orders', 'id-1'));
        $this->connection->commit();

        $this->connection->beginTransaction();
        $this->assertFalse($inbox->record('orders', 'id-1'));
        $this->connection->rollBack();
    }

    public function testEachConsumerRecordsTheMessage()
    {
        $inbox = $this->createInbox();

        $this->connection->beginTransaction();
        $this->assertTrue($inbox->record('orders', 'id-1'));
        $this->assertTrue($inbox->record('invoices', 'id-1'));
        $this->connection->commit();
    }

    public function testARolledBackRecordIsDiscarded()
    {
        $inbox = $this->createInbox();

        $this->connection->beginTransaction();
        $this->assertTrue($inbox->record('orders', 'id-1'));
        $this->connection->rollBack();

        $this->connection->beginTransaction();
        $this->assertTrue($inbox->record('orders', 'id-1'));
        $this->connection->commit();
    }

    public function testTheTransactionStaysUsableAfterAMessageWasRecordedAlready()
    {
        $inbox = $this->createInbox();

        $this->connection->beginTransaction();
        $inbox->record('orders', 'id-1');
        $this->connection->commit();

        $this->connection->beginTransaction();
        $this->assertFalse($inbox->record('orders', 'id-1'));
        $this->assertTrue($inbox->record('orders', 'id-2'));
        $this->connection->commit();

        $this->assertSame(['id-1', 'id-2'], $this->connection->fetchFirstColumn('SELECT message_id FROM messenger_inbox ORDER BY message_id'));
    }

    public function testRecordingNeedsATransaction()
    {
        $inbox = $this->createInbox();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot record message "id-1" for "orders" outside of a database transaction. Run the handlers in a transaction on the connection of the inbox, as the "doctrine_transaction" middleware does.');

        $inbox->record('orders', 'id-1');
    }

    public function testAForgottenMessageCanBeRecordedAgain()
    {
        $inbox = $this->createInbox();

        $this->connection->beginTransaction();
        $inbox->record('orders', 'id-1');
        $inbox->forget('orders', 'id-1');
        $this->assertTrue($inbox->record('orders', 'id-1'));
        $this->connection->commit();
    }

    public function testRecordingRemovesTheExpiredRecordsOfTheConsumer()
    {
        $inbox = $this->createInbox(60, 1.0);
        $old = new \DateTimeImmutable('-61 seconds', new \DateTimeZone('UTC'));
        $recent = new \DateTimeImmutable('-59 seconds', new \DateTimeZone('UTC'));

        $this->connection->insert('messenger_inbox', ['consumer' => 'orders', 'message_id' => 'old', 'handled_at' => $old], ['handled_at' => Types::DATETIME_IMMUTABLE]);
        $this->connection->insert('messenger_inbox', ['consumer' => 'orders', 'message_id' => 'recent', 'handled_at' => $recent], ['handled_at' => Types::DATETIME_IMMUTABLE]);
        $this->connection->insert('messenger_inbox', ['consumer' => 'invoices', 'message_id' => 'old', 'handled_at' => $old], ['handled_at' => Types::DATETIME_IMMUTABLE]);

        $this->connection->beginTransaction();
        $inbox->record('orders', 'new');
        $this->connection->commit();

        $this->assertSame([['invoices', 'old'], ['orders', 'new'], ['orders', 'recent']], $this->connection->fetchAllNumeric('SELECT consumer, message_id FROM messenger_inbox ORDER BY consumer, message_id'));
    }

    public function testSetupCanRunAgain()
    {
        $inbox = $this->createInbox();
        $inbox->setup();

        $this->assertTrue($this->connection->createSchemaManager()->tablesExist(['messenger_inbox']));
    }

    public function testConfigureSchemaAddsTheTable()
    {
        $inbox = new DoctrineInbox($this->connection, 'my_inbox');

        $schema = $inbox->configureSchema(new Schema(), $this->connection, static fn () => true);

        $this->assertTrue($schema->hasTable('my_inbox'));
        $table = $schema->getTable('my_inbox');
        $this->assertTrue($table->hasColumn('consumer'));
        $this->assertTrue($table->hasColumn('message_id'));
        $this->assertTrue($table->hasColumn('handled_at'));
    }

    public function testConfigureSchemaSkipsAnotherDatabase()
    {
        $inbox = new DoctrineInbox($this->connection);
        $otherConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $schema = $inbox->configureSchema(new Schema(), $otherConnection, static fn () => false);

        $this->assertFalse($schema->hasTable('messenger_inbox'));
    }

    private function createInbox(int $retention = 604800, float $gcProbability = 0.0): DoctrineInbox
    {
        $inbox = new DoctrineInbox($this->connection, 'messenger_inbox', $retention, $gcProbability);
        $inbox->setup();

        return $inbox;
    }
}
