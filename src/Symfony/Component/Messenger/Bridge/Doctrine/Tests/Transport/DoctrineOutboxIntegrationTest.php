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
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

#[RequiresPhpExtension('pdo_sqlite')]
class DoctrineOutboxIntegrationTest extends TestCase
{
    private \Doctrine\DBAL\Connection $driverConnection;
    private DoctrineTransport $outbox;
    private InMemoryTransport $target;
    private MessageBus $bus;

    protected function setUp(): void
    {
        if (!class_exists(OutboxSender::class)) {
            $this->markTestSkipped('This test requires symfony/messenger 8.2 or higher.');
        }

        $dsn = getenv('MESSENGER_DOCTRINE_DSN') ?: 'pdo-sqlite://:memory:';
        $params = (new DsnParser())->parse($dsn);
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $this->driverConnection = DriverManager::getConnection($params, $config);
        $this->outbox = new DoctrineTransport(new Connection([], $this->driverConnection), new PhpSerializer());
        $this->outbox->setup();
        $this->target = new InMemoryTransport();

        $senders = new class(['orders' => new OutboxSender($this->target, $this->outbox, 'orders')]) implements ContainerInterface {
            public function __construct(private array $senders)
            {
            }

            public function get(string $id): mixed
            {
                return $this->senders[$id];
            }

            public function has(string $id): bool
            {
                return isset($this->senders[$id]);
            }
        };

        $this->bus = new MessageBus([new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['orders']], $senders))]);
    }

    protected function tearDown(): void
    {
        $this->driverConnection->close();
    }

    public function testTheStoredMessageIsRolledBackWithTheBusinessTransaction()
    {
        $this->driverConnection->beginTransaction();
        $this->bus->dispatch(new DummyMessage('Hey'));
        $this->driverConnection->rollBack();

        $this->assertSame(0, $this->countStoredMessages());
        $this->assertSame([], $this->target->getSent());
    }

    public function testTheStoredMessageIsCommittedWithTheBusinessTransaction()
    {
        $this->driverConnection->beginTransaction();
        $this->bus->dispatch(new DummyMessage('Hey'));
        $this->driverConnection->commit();

        $this->assertSame(1, $this->countStoredMessages());
        $this->assertSame([], $this->target->getSent(), 'the target gets the message when the outbox is consumed, not when it is stored');

        $stored = $this->outbox->get();

        $this->assertCount(1, $stored);
        $this->assertSame('orders', $stored[0]->last(OutboxStamp::class)?->getTransportName());
    }

    private function countStoredMessages(): int
    {
        return (int) $this->driverConnection->fetchOne('SELECT COUNT(*) FROM messenger_messages');
    }
}
