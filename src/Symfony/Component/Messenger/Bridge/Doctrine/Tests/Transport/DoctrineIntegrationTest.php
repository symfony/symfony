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
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

/**
 * @requires extension pdo_sqlite
 */
class DoctrineIntegrationTest extends TestCase
{
    private \Doctrine\DBAL\Connection $driverConnection;
    private Connection $connection;

    protected function setUp(): void
    {
        $dsn = getenv('MESSENGER_DOCTRINE_DSN') ?: 'pdo-sqlite://:memory:';
        $params = class_exists(DsnParser::class) ? (new DsnParser())->parse($dsn) : ['url' => $dsn];
        $config = new Configuration();
        if (class_exists(DefaultSchemaManagerFactory::class)) {
            $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }

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

        $stmt = $this->driverConnection->createQueryBuilder()
            ->select('m.available_at')
            ->from('messenger_messages', 'm')
            ->where('m.body = :body')
            ->setParameter('body', '{"message": "Hi i am delayed"}');
        if (method_exists($stmt, 'executeQuery')) {
            $stmt = $stmt->executeQuery();
        } else {
            $stmt = $stmt->execute();
        }

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
        $twoHoursAgo = new \DateTimeImmutable('now -2 hours');
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
        $this->assertFalse($this->createSchemaManager()->tablesExist(['messenger_messages']));
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
