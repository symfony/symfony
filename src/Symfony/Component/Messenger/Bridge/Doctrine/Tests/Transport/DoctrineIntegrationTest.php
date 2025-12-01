<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Middleware;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

#[RequiresPhpExtension('pdo_sqlite')]
class DoctrineIntegrationTest extends TestCase
{
    private \Doctrine\DBAL\Connection $driverConnection;
    private Connection $connection;
    private DebugDataHolder $debugDataHolder;

    protected function setUp(): void
    {
        $dsn = getenv('MESSENGER_DOCTRINE_DSN') ?: 'pdo-sqlite://:memory:';
        $params = (new DsnParser())->parse($dsn);
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $this->debugDataHolder = new DebugDataHolder();
        $config->setMiddlewares([new Middleware($this->debugDataHolder, null)]);

        $this->driverConnection = DriverManager::getConnection($params, $config);
        $this->connection = new Connection([], $this->driverConnection);
    }

    protected function tearDown(): void
    {
        $this->driverConnection->close();
    }

    public function testConnectionSendAndGet()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded['headers']);
    }

    public function testSendWithDelay()
    {
        $this->connection->send('{"message": "Hi i am delayed"}', ['type' => DummyMessage::class], 600000);

        $qb = $this->driverConnection->createQueryBuilder()
            ->select('m.available_at')
            ->from('messenger_messages', 'm')
            ->where('m.body = :body')
            ->setParameter('body', '{"message": "Hi i am delayed"}');

        $result = $qb->executeQuery();

        $availableAt = new \DateTimeImmutable($result->fetchOne(), new \DateTimeZone('UTC'));

        $now = new \DateTimeImmutable('now + 60 seconds', new \DateTimeZone('UTC'));
        $this->assertGreaterThan($now, $availableAt);
    }

    public function testSendWithNegativeDelay()
    {
        $this->connection->send('{"message": "Hi, I am not actually delayed"}', ['type' => DummyMessage::class], -600000);

        $qb = $this->driverConnection->createQueryBuilder()
            ->select('m.available_at')
            ->from('messenger_messages', 'm')
            ->where('m.body = :body')
            ->setParameter('body', '{"message": "Hi, I am not actually delayed"}');

        $result = $qb->executeQuery();

        $availableAt = new \DateTimeImmutable($result->fetchOne(), new \DateTimeZone('UTC'));

        $now = new \DateTimeImmutable('now - 60 seconds', new \DateTimeZone('UTC'));
        $this->assertLessThan($now, $availableAt);
    }

    public function testItRetrieveTheFirstAvailableMessage()
    {
        $this->connection->setup();
        // insert messages
        // one currently handled
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi handled"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'delivered_at' => $this->formatDateTime(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
        ]);
        // one available later
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi delayed"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 13:00:00', new \DateTimeZone('UTC'))),
        ]);
        // one available
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:30:00', new \DateTimeZone('UTC'))),
        ]);

        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi available"}', $encoded['body']);
    }

    public function testItCountMessages()
    {
        $this->connection->setup();
        // insert messages
        // one currently handled
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi handled"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'delivered_at' => $this->formatDateTime(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))),
        ]);
        // one available later
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi delayed"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('+1 minute', new \DateTimeZone('UTC'))),
        ]);
        // one available
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:30:00', new \DateTimeZone('UTC'))),
        ]);
        // another available
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:30:00', new \DateTimeZone('UTC'))),
        ]);

        $this->assertSame(2, $this->connection->getMessageCount());
    }

    public function testItRetrieveTheMessageThatIsOlderThanRedeliverTimeout()
    {
        $this->connection->setup();
        $twoHoursAgo = new \DateTimeImmutable('now -2 hours', new \DateTimeZone('UTC'));
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi requeued"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'delivered_at' => $this->formatDateTime($twoHoursAgo),
        ]);
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'))),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:30:00', new \DateTimeZone('UTC'))),
        ]);

        $next = $this->connection->get();
        $this->assertEquals('{"message": "Hi requeued"}', $next['body']);
        $this->connection->reject($next['id']);
    }

    public function testTheTransportIsSetupOnGet()
    {
        $this->driverConnection->executeStatement('CREATE TABLE unrelated (unknown_type_column)');
        $this->assertFalse($this->driverConnection->createSchemaManager()->tablesExist(['messenger_messages']));
        $this->assertNull($this->connection->get());

        $this->connection->send('the body', ['my' => 'header']);
        $envelope = $this->connection->get();
        $this->assertEquals('the body', $envelope['body']);
    }

    public function testSendBatch()
    {
        // Setup connection, and reset the debug data holder to only capture the batch insert query
        $this->connection->setup();
        $this->debugDataHolder->reset();

        $this->connection->sendBatch([
            ['body' => '{"message": "First"}', 'headers' => ['type' => DummyMessage::class], 'delay' => 0],
            ['body' => '{"message": "Second"}', 'headers' => ['type' => DummyMessage::class], 'delay' => 0],
            ['body' => '{"message": "Third"}', 'headers' => ['type' => DummyMessage::class], 'delay' => 0],
        ]);

        $queries = $this->debugDataHolder->getData()['default'] ?? [];
        $this->assertCount(1, $queries, 'Batch insert should execute exactly 1 SQL statement');
        $this->assertStringStartsWith('INSERT INTO', $queries[0]['sql']);
        $this->assertSame(3, $this->connection->getMessageCount());

        $first = $this->connection->get();
        $this->assertEquals('{"message": "First"}', $first['body']);
        $this->connection->ack($first['id']);

        $second = $this->connection->get();
        $this->assertEquals('{"message": "Second"}', $second['body']);
        $this->connection->ack($second['id']);

        $third = $this->connection->get();
        $this->assertEquals('{"message": "Third"}', $third['body']);
        $this->connection->ack($third['id']);

        $this->assertSame(0, $this->connection->getMessageCount());
    }

    public function testSendBatchWithDelay()
    {
        $this->connection->sendBatch([
            ['body' => '{"message": "Immediate"}', 'headers' => ['type' => DummyMessage::class], 'delay' => 0],
            ['body' => '{"message": "Delayed"}', 'headers' => ['type' => DummyMessage::class], 'delay' => 600000],
        ]);

        // Only the immediate message should be available
        $this->assertSame(1, $this->connection->getMessageCount());

        $immediate = $this->connection->get();
        $this->assertEquals('{"message": "Immediate"}', $immediate['body']);
    }

    public function testSendBatchEmpty()
    {
        $this->connection->setup();
        $this->connection->sendBatch([]);

        $this->assertSame(0, $this->connection->getMessageCount());
    }

    public function testSendBatchSingleMessage()
    {
        $this->connection->sendBatch([
            ['body' => '{"message": "Only one"}', 'headers' => ['type' => DummyMessage::class], 'delay' => 0],
        ]);

        $this->assertSame(1, $this->connection->getMessageCount());

        $message = $this->connection->get();
        $this->assertEquals('{"message": "Only one"}', $message['body']);
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->format($this->driverConnection->getDatabasePlatform()->getDateTimeFormatString());
    }
}
