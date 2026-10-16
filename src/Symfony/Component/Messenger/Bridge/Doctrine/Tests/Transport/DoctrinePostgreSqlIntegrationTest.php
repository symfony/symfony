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

    public function testSkipLocked()
    {
        $connection = new PostgreSqlConnection(['table_name' => 'queue_table', 'skip_locked' => true], $this->driverConnection);

        $connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);

        $encoded = $connection->get();
        $this->assertEquals('{"message": "Hi"}', $encoded[0]['body']);
        $this->assertEquals(['type' => DummyMessage::class], $encoded[0]['headers']);

        $this->assertNull($connection->get());
    }

    private function terminateBackend(): void
    {
        $admin = DriverManager::getConnection($this->driverConnection->getParams(), $this->driverConnection->getConfiguration());
        $admin->executeQuery('SELECT pg_terminate_backend(?, 5000)', [$this->driverConnection->fetchOne('SELECT pg_backend_pid()')]);
        $admin->close();
    }
}
