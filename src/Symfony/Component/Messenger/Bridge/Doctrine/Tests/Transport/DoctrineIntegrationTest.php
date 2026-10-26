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
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

#[RequiresPhpExtension('pdo_sqlite')]
class DoctrineIntegrationTest extends TestCase
{
    private \Doctrine\DBAL\Connection $driverConnection;
    private Connection $connection;

    protected function setUp(): void
    {
        $dsn = getenv('MESSENGER_DOCTRINE_DSN') ?: 'pdo-sqlite://:memory:';
        $params = (new DsnParser())->parse($dsn);
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

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
        $this->assertEquals('{"message": "Hi"}', $encoded[0]['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded[0]['headers']);
    }

    public function testKeepaliveRenewsTheLease()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $encoded = $this->connection->get();
        $this->driverConnection->executeStatement('UPDATE messenger_messages SET delivered_at = ? WHERE id = ?', ['2000-01-01 00:00:00', $encoded[0]['id']]);

        $this->connection->keepalive($encoded[0]['id']);

        $this->assertNotSame('2000-01-01 00:00:00', $this->driverConnection->fetchOne('SELECT delivered_at FROM messenger_messages WHERE id = ?', [$encoded[0]['id']]));
    }

    public function testKeepaliveWhileTheConnectionIsInATransaction()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $encoded = $this->connection->get();
        $this->driverConnection->executeStatement('UPDATE messenger_messages SET delivered_at = ? WHERE id = ?', ['2000-01-01 00:00:00', $encoded[0]['id']]);

        $this->driverConnection->beginTransaction();
        $this->connection->keepalive($encoded[0]['id']);
        $this->driverConnection->commit();

        $this->assertNotSame('2000-01-01 00:00:00', $this->driverConnection->fetchOne('SELECT delivered_at FROM messenger_messages WHERE id = ?', [$encoded[0]['id']]));
    }

    #[Group('time-sensitive')]
    public function testDatesAreComputedFromNow()
    {
        ClockMock::withClockMock(1000000000.5);

        $id = $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class], 2000);
        $this->connection->sendBatch([['{"message": "Hi again"}', ['type' => DummyMessage::class], 2000]]);

        $this->assertSame([
            ['2001-09-09 01:46:40', '2001-09-09 01:46:43'],
            ['2001-09-09 01:46:40', '2001-09-09 01:46:43'],
        ], $this->driverConnection->fetchAllNumeric('SELECT created_at, available_at FROM messenger_messages ORDER BY id'));
        $this->assertNull($this->connection->get());

        sleep(3);
        $this->assertEquals($id, $this->connection->get()[0]['id']);
        $this->assertSame('2001-09-09 01:46:43', $this->driverConnection->fetchOne('SELECT delivered_at FROM messenger_messages WHERE id = ?', [$id]));

        sleep(10);
        $this->connection->keepalive($id);
        $this->assertSame('2001-09-09 01:46:53', $this->driverConnection->fetchOne('SELECT delivered_at FROM messenger_messages WHERE id = ?', [$id]));
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
        $this->assertEquals('{"message": "Hi available"}', $encoded[0]['body']);
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
        $this->assertEquals('{"message": "Hi requeued"}', $next[0]['body']);
        $this->connection->reject($next[0]['id']);
    }

    public function testFindAllInBatchesListsEachAvailableMessageOnce()
    {
        $this->connection->setup();
        $this->insertMessages(range(1, 1100), new \DateTimeImmutable('2019-03-15 12:30:00', new \DateTimeZone('UTC')));
        $this->insertMessages(range(1101, 1200), new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC')));
        $this->insertMessages([1201], new \DateTimeImmutable('2019-03-15 11:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('now -2 hours', new \DateTimeZone('UTC')));
        $this->insertMessages([1202], new \DateTimeImmutable('2019-03-15 11:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->insertMessages([1203], new \DateTimeImmutable('+1 minute', new \DateTimeZone('UTC')));
        $this->insertMessages([1204], new \DateTimeImmutable('2019-03-15 11:00:00', new \DateTimeZone('UTC')), null, 'other');
        $this->insertMessages(range(1205, 1210), new \DateTimeImmutable('2019-03-15 12:45:00', new \DateTimeZone('UTC')));

        $bodies = array_column(iterator_to_array($this->connection->findAllInBatches(), false), 'body');

        $this->assertSame(array_map(strval(...), [...range(1101, 1200), ...range(1, 1100), ...range(1205, 1210), 1201]), $bodies);
    }

    public function testFindAllInBatchesListsTheTimedOutMessagesByDeliveryDate()
    {
        $this->connection->setup();
        $availableAt = new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'));
        $this->insertMessages(range(1, 70), $availableAt, new \DateTimeImmutable('2019-03-15 13:00:03', new \DateTimeZone('UTC')));
        $this->insertMessages(range(71, 220), $availableAt, new \DateTimeImmutable('2019-03-15 13:00:01', new \DateTimeZone('UTC')));
        $this->insertMessages(range(221, 270), $availableAt, new \DateTimeImmutable('2019-03-15 13:00:02', new \DateTimeZone('UTC')));
        $this->insertMessages(range(271, 280), new \DateTimeImmutable('2019-03-15 12:00:01', new \DateTimeZone('UTC')), new \DateTimeImmutable('2019-03-15 13:00:00', new \DateTimeZone('UTC')));
        $this->insertMessages([281], new \DateTimeImmutable('2019-03-15 12:00:30', new \DateTimeZone('UTC')));

        $bodies = array_column(iterator_to_array($this->connection->findAllInBatches(), false), 'body');

        $this->assertSame(array_map(strval(...), [281, ...range(71, 220), ...range(221, 270), ...range(1, 70), ...range(271, 280)]), $bodies);
    }

    public function testFindAllInBatchesReadsTheMessagesBatchByBatch()
    {
        $this->connection->setup();
        $this->insertMessages(range(1, 1100), new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC')));

        $messages = $this->connection->findAllInBatches();
        $this->assertSame('1', $messages->current()['body']);

        $this->driverConnection->delete('messenger_messages', ['body' => '50']);
        $this->driverConnection->delete('messenger_messages', ['body' => '130']);
        $this->driverConnection->update('messenger_messages', ['delivered_at' => $this->formatDateTime(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))], ['body' => '1050']);
        $this->insertMessages([1101], new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC')));

        $bodies = array_column(iterator_to_array($messages, false), 'body');

        $this->assertSame(array_map(strval(...), array_values(array_diff(range(1, 1100), [130, 1050]))), $bodies);
    }

    public function testFindAllInBatchesDoesNotListTheMessagesSentDuringTheListing()
    {
        $this->connection->setup();
        $availableAt = new \DateTimeImmutable('2019-03-15 12:00:00', new \DateTimeZone('UTC'));
        $this->insertMessages(range(1, 150), $availableAt);

        $bodies = [];
        foreach ($this->connection->findAllInBatches() as $message) {
            $bodies[] = $message['body'];

            if (300 === \count($bodies)) {
                break;
            }

            $this->insertMessages([$message['body']], $availableAt);
        }

        $this->assertSame(array_map(strval(...), range(1, 150)), $bodies);
    }

    public function testTheTransportIsSetupOnGet()
    {
        $this->driverConnection->executeStatement('CREATE TABLE unrelated (unknown_type_column)');
        $this->assertFalse($this->driverConnection->createSchemaManager()->tablesExist(['messenger_messages']));
        $this->assertNull($this->connection->get());

        $this->connection->send('the body', ['my' => 'header']);
        $envelope = $this->connection->get();
        $this->assertEquals('the body', $envelope[0]['body']);
    }

    public function testSendBatch()
    {
        $statements = [];
        $connection = $this->createLoggedConnection($statements);
        $connection->setup();

        $connection->sendBatch([
            'a' => ['{"message": "a"}', ['type' => DummyMessage::class]],
            'b' => ['{"message": "b"}', ['type' => DummyMessage::class], 600000],
            'c' => ['{"message": "c"}', ['type' => DummyMessage::class], 0],
        ]);

        $this->assertCount(1, array_filter($statements, static fn (string $sql) => str_starts_with($sql, 'INSERT')));
        $this->assertSame(2, $connection->getMessageCount());
        $this->assertSame('{"message": "a"}', $connection->get()[0]['body']);
        $this->assertSame('{"message": "c"}', $connection->get()[0]['body']);
        $this->assertNull($connection->get());
    }

    public function testSendBatchSplitsLargeBatches()
    {
        $statements = [];
        $connection = $this->createLoggedConnection($statements);
        $connection->setup();

        $messages = [];
        for ($i = 0; $i < 250; ++$i) {
            $messages[] = ['{"message": "'.$i.'"}', []];
        }
        $messages[] = [str_repeat('a', 600000), []];
        $messages[] = [str_repeat('b', 600000), []];
        $connection->sendBatch($messages);

        $this->assertCount(4, array_filter($statements, static fn (string $sql) => str_starts_with($sql, 'INSERT')));
        $this->assertSame(252, $connection->getMessageCount());
    }

    public function testSendBatchSetsUpTheTable()
    {
        $this->connection->sendBatch([['{"message": "a"}', []], ['{"message": "b"}', []]]);

        $this->assertSame(2, $this->connection->getMessageCount());
    }

    private function createLoggedConnection(array &$statements): Connection
    {
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $config->setMiddlewares([new Middleware(new class($statements) extends AbstractLogger {
            public function __construct(
                private array &$statements,
            ) {
            }

            public function log($level, $message, array $context = []): void
            {
                if (isset($context['sql'])) {
                    $this->statements[] = $context['sql'];
                }
            }
        })]);

        $this->driverConnection = DriverManager::getConnection($this->driverConnection->getParams(), $config);

        return new Connection([], $this->driverConnection);
    }

    private function insertMessages(array $bodies, \DateTimeImmutable $availableAt, ?\DateTimeImmutable $deliveredAt = null, string $queueName = 'default'): void
    {
        foreach ($bodies as $body) {
            $this->driverConnection->insert('messenger_messages', [
                'body' => (string) $body,
                'headers' => '[]',
                'queue_name' => $queueName,
                'created_at' => $this->formatDateTime($availableAt),
                'available_at' => $this->formatDateTime($availableAt),
                'delivered_at' => $deliveredAt ? $this->formatDateTime($deliveredAt) : null,
            ]);
        }
    }

    private function formatDateTime(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->format($this->driverConnection->getDatabasePlatform()->getDateTimeFormatString());
    }
}
