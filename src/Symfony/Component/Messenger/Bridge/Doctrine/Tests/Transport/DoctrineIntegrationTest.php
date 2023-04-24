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

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

/**
 * @requires extension pdo_sqlite
 */
class DoctrineIntegrationTest extends TestCase
{
    /** @var \Doctrine\DBAL\Connection */
    private $driverConnection;
    /** @var Connection */
    private $connection;

    protected function setUp(): void
    {
        $dsn = getenv('MESSENGER_DOCTRINE_DSN') ?: 'sqlite://:memory:';
        $this->driverConnection = DriverManager::getConnection(['url' => $dsn]);
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

        $stmt = $this->driverConnection->createQueryBuilder()
            ->select('m.available_at')
            ->from('messenger_messages', 'm')
            ->where('m.body = :body')
            ->setParameter('body', '{"message": "Hi i am delayed"}')
            ->execute();

        $available_at = new \DateTimeImmutable($stmt instanceof Result ? $stmt->fetchOne() : $stmt->fetchColumn());

        $now = new \DateTimeImmutable('now + 60 seconds');
        $this->assertGreaterThan($now, $available_at);
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
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'delivered_at' => $this->formatDateTime(new \DateTimeImmutable()),
        ]);
        // one available later
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi delayed"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 13:00:00')),
        ]);
        // one available
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:30:00')),
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
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'delivered_at' => $this->formatDateTime(new \DateTimeImmutable()),
        ]);
        // one available later
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi delayed"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime((new \DateTimeImmutable())->modify('+1 minute')),
        ]);
        // one available
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:30:00')),
        ]);
        // another available
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:30:00')),
        ]);

        $this->assertSame(2, $this->connection->getMessageCount());
    }

    public function testItRetrieveTheMessageThatIsOlderThanRedeliverTimeout()
    {
        $this->connection->setup();
        $twoHoursAgo = new \DateTimeImmutable('now -2 hours');
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi requeued"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'delivered_at' => $this->formatDateTime($twoHoursAgo),
        ]);
        $this->driverConnection->insert('messenger_messages', [
            'body' => '{"message": "Hi available"}',
            'headers' => json_encode(['type' => DummyMessage::class]),
            'queue_name' => 'default',
            'created_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:00:00')),
            'available_at' => $this->formatDateTime(new \DateTimeImmutable('2019-03-15 12:30:00')),
        ]);

        $next = $this->connection->get();
        $this->assertEquals('{"message": "Hi requeued"}', $next['body']);
        $this->connection->reject($next['id']);
    }

    public function testTheTransportIsSetupOnGet()
    {
        $this->assertFalse($this->createSchemaManager()->tablesExist('messenger_messages'));
        $this->assertNull($this->connection->get());

        $this->connection->send('the body', ['my' => 'header']);
        $envelope = $this->connection->get();
        $this->assertEquals('the body', $envelope['body']);
    }

    private function formatDateTime(\DateTimeImmutable $dateTime)
    {
        return $dateTime->format($this->driverConnection->getDatabasePlatform()->getDateTimeFormatString());
    }

    private function createSchemaManager(): AbstractSchemaManager
    {
        return method_exists($this->driverConnection, 'createSchemaManager')
            ? $this->driverConnection->createSchemaManager()
            : $this->driverConnection->getSchemaManager();
    }
}
