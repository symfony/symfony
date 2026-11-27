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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineReceiver;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;
use Symfony\Component\Messenger\Cooldown\MultiplierCooldownStrategy;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;

#[RequiresPhpExtension('pdo_pgsql')]
#[Group('integration')]
class DoctrinePostgreSqlIntegrationTest extends TestCase
{
    private Connection $driverConnection;
    private PostgreSqlConnection $connection;

    protected function setUp(): void
    {
        if (!$host = getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $url = "pdo-pgsql://postgres:password@$host";
        $params = (new DsnParser())->parse($url);
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $this->driverConnection = DriverManager::getConnection($params, $config);
        $this->connection = new PostgreSqlConnection(['table_name' => 'queue_table'], $this->driverConnection);
        $this->connection->setup();
    }

    protected function tearDown(): void
    {
        if (!isset($this->driverConnection)) {
            return;
        }
        $this->driverConnection->createSchemaManager()->dropTable('queue_table');
        $this->driverConnection->close();
    }

    public function testPostgreSqlConnectionSendAndGet()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $encoded = $this->connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded[0]['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded[0]['headers']);

        $this->assertNull($this->connection->get());
    }

    public function testSendBatchReturnsTheIds()
    {
        $ids = $this->connection->sendBatch(['a' => ['{"message": "a"}', []], 'b' => ['{"message": "b"}', []]]);

        $this->assertSame(['a', 'b'], array_keys($ids));
        $this->assertSame($ids['a'], (string) $this->connection->get()[0]['id']);
        $this->assertSame($ids['b'], (string) $this->connection->get()[0]['id']);
    }

    public function testWorkerWithACooldownStrategySurvivesALostConnection()
    {
        $this->connection->send((new PhpSerializer())->encode(new Envelope(new DummyMessage('Hi')))['body'], []);
        $this->terminateBackend();

        $handled = [];
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            DummyMessage::class => [function (DummyMessage $message) use (&$handled) {
                $handled[] = $message->getMessage();
                $this->terminateBackend();
            }],
        ]))]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $worker = new Worker(['async' => new DoctrineReceiver($this->connection)], $bus, $dispatcher, null, null, new Clock(), null, ['async' => new MultiplierCooldownStrategy(1, 0)]);
        $worker->run(['sleep' => 1000, 'time_limit' => 10]);

        $this->assertSame(['Hi'], $handled);
        $this->assertSame(0, (int) $this->driverConnection->fetchOne('SELECT COUNT(*) FROM queue_table'));
    }

    public function testReleaseMakesTheMessagesAvailableAgain()
    {
        foreach (['first', 'second', 'third'] as $message) {
            $this->connection->send('{"message": "'.$message.'"}', ['type' => DummyMessage::class]);
        }
        $encoded = $this->connection->get(3);

        $this->connection->release($encoded[1]['id']);
        $this->connection->release($encoded[2]['id']);

        $this->assertNotNull($this->getDeliveredAt($encoded[0]['id']));
        $this->assertNull($this->getDeliveredAt($encoded[1]['id']));
        $this->assertNull($this->getDeliveredAt($encoded[2]['id']));
        // another worker gets them at once, without the message that was not released
        $otherConnection = new PostgreSqlConnection(['table_name' => 'queue_table'], $this->driverConnection);
        $this->assertSame(['{"message": "second"}', '{"message": "third"}'], array_column($otherConnection->get(3), 'body'));
    }

    #[Group('time-sensitive')]
    public function testReleaseAndALateKeepaliveLeaveAMessageThatAnotherWorkerReceivedSince()
    {
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table', 'redeliver_timeout' => 10], $this->driverConnection);
        $otherConnection = new PostgreSqlConnection(['table_name' => 'queue_table', 'redeliver_timeout' => 10], $this->driverConnection);
        $connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $id = $connection->get()[0]['id'];

        // the redeliver timeout passes without a keepalive: another worker receives the message
        sleep(11);
        $this->assertSame($id, $otherConnection->get()[0]['id']);
        $deliveredAt = $this->getDeliveredAt($id);

        sleep(1);
        $connection->keepalive($id);
        $this->assertSame($deliveredAt, $this->getDeliveredAt($id));

        $connection->release($id);
        $this->assertSame($deliveredAt, $this->getDeliveredAt($id));
        $this->assertNull((new PostgreSqlConnection(['table_name' => 'queue_table', 'redeliver_timeout' => 10], $this->driverConnection))->get());

        // the message stays with the worker that received it last
        $otherConnection->keepalive($id);
        $this->assertGreaterThan($deliveredAt, $this->getDeliveredAt($id));
        $otherConnection->release($id);
        $this->assertNull($this->getDeliveredAt($id));
    }

    public function testReleaseNotifiesTheWorkersThatWaitForAMessage()
    {
        $waitingConnection = new PostgreSqlConnection(['table_name' => 'queue_table'], DriverManager::getConnection($this->driverConnection->getParams(), $this->driverConnection->getConfiguration()));
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $id = $this->connection->get()[0]['id'];
        $waitingConnection->listen();
        $this->assertNull($waitingConnection->get());

        // nothing is released: no worker is woken up
        (new PostgreSqlConnection(['table_name' => 'queue_table'], $this->driverConnection))->release($id);
        $this->assertFalse($waitingConnection->waitForNotify(200));

        $this->connection->release($id);

        $this->assertTrue($waitingConnection->waitForNotify(10000));
        $this->assertSame($id, $waitingConnection->get()[0]['id']);
    }

    public function testAWorkerThatStopsInTheMiddleOfABatchLeavesTheRestAvailable()
    {
        $serializer = new PhpSerializer();
        foreach (['first', 'second', 'third'] as $message) {
            $this->connection->send($serializer->encode(new Envelope(new DummyMessage($message)))['body'], []);
        }
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        // what "messenger:consume --fetch-size=3 --limit=1" does
        (new Worker(['async' => new DoctrineReceiver($this->connection)], new MessageBus(), $dispatcher))->run(['sleep' => 0, 'fetch_size' => 3]);

        $this->assertSame([null, null], $this->driverConnection->fetchFirstColumn('SELECT delivered_at FROM queue_table ORDER BY id'));
        $envelopes = (new DoctrineReceiver(new PostgreSqlConnection(['table_name' => 'queue_table'], $this->driverConnection)))->get(3);
        $this->assertSame(['second', 'third'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $envelopes));
    }

    public function testSkipLocked()
    {
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table', 'skip_locked' => true], $this->driverConnection);

        $connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $encoded = $connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded[0]['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded[0]['headers']);

        $this->assertNull($connection->get());
    }

    private function getDeliveredAt(string $id): ?string
    {
        return $this->driverConnection->fetchOne('SELECT delivered_at FROM queue_table WHERE id = ?', [$id]);
    }

    private function terminateBackend(): void
    {
        $admin = DriverManager::getConnection($this->driverConnection->getParams(), $this->driverConnection->getConfiguration());
        $admin->executeQuery('SELECT pg_terminate_backend(?, 5000)', [$this->driverConnection->fetchOne('SELECT pg_backend_pid()')]);
        $admin->close();
    }
}
