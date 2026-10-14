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
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Bridge\Doctrine\Inbox\DoctrineInbox;
use Symfony\Component\Messenger\Bridge\Doctrine\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\InboxMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Contracts\Service\ServiceLocatorTrait;

#[RequiresPhpExtension('pdo_sqlite')]
class DoctrineInboxIntegrationTest extends TestCase
{
    private DBALConnection $connection;
    private DoctrineInboxIntegrationTestSender $outbox;
    private InMemoryTransport $invoices;
    private DoctrineInboxIntegrationTestHandler $handler;
    private MessageBus $bus;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        $this->connection = DriverManager::getConnection((new DsnParser())->parse(getenv('MESSENGER_DOCTRINE_DSN') ?: 'pdo-sqlite://:memory:'), $config);
        foreach (['messenger_inbox', 'messenger_messages', 'orders'] as $table) {
            $this->connection->executeStatement("DROP TABLE IF EXISTS $table");
        }
        $this->connection->executeStatement('CREATE TABLE orders (name VARCHAR(255) NOT NULL)');

        $inbox = new DoctrineInbox($this->connection);
        $inbox->setup();
        $outbox = new DoctrineTransport(new Connection([], $this->connection), new PhpSerializer());
        $outbox->setup();
        $this->outbox = new DoctrineInboxIntegrationTestSender($outbox);
        $this->invoices = new InMemoryTransport();

        $sendersLocator = new SendersLocator([DoctrineInboxIntegrationTestEvent::class => ['orders']], new DoctrineInboxIntegrationTestLocator([
            'orders' => fn () => new OutboxSender(new InMemoryTransport(), $this->outbox, 'orders', 'outbox'),
            'invoices' => fn () => $this->invoices,
        ]));
        $inboxMiddleware = new InboxMiddleware(new DoctrineInboxIntegrationTestLocator(['orders' => static fn () => $inbox]));
        $this->handler = new DoctrineInboxIntegrationTestHandler($this->connection);

        $this->bus = new MessageBus([
            new DispatchAfterCurrentBusMiddleware($sendersLocator, $inboxMiddleware),
            new DoctrineInboxIntegrationTestTransactionMiddleware($this->connection),
            new SendMessageMiddleware($sendersLocator),
            $inboxMiddleware,
            new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [$this->handler]])),
        ]);
        $this->handler->bus = $this->bus;
    }

    protected function tearDown(): void
    {
        foreach (['messenger_inbox', 'messenger_messages', 'orders'] as $table) {
            $this->connection->executeStatement("DROP TABLE IF EXISTS $table");
        }
        $this->connection->close();
    }

    public function testARedeliveredMessageIsNotHandledAgain()
    {
        $this->bus->dispatch($this->createReceivedEnvelope());
        $this->bus->dispatch($this->createReceivedEnvelope());

        $this->assertSame(1, $this->handler->calls);
        $this->assertSame(['Hey'], $this->connection->fetchFirstColumn('SELECT name FROM orders'));
    }

    public function testAFailedMessageIsHandledAgainWhenItIsRetried()
    {
        $this->handler->failures = 1;

        try {
            $this->bus->dispatch($this->createReceivedEnvelope());
            $this->fail('The handler should have failed.');
        } catch (HandlerFailedException) {
        }

        $this->assertSame(0, $this->countRows('orders'));
        $this->assertSame(0, $this->countRows('messenger_inbox'));

        $this->bus->dispatch($this->createReceivedEnvelope());

        $this->assertSame(2, $this->handler->calls);
        $this->assertSame(['Hey'], $this->connection->fetchFirstColumn('SELECT name FROM orders'));
    }

    public function testAMessageDispatchedAfterTheCurrentOneIsNotLostWhenStoringItFails()
    {
        $this->handler->dispatch = new Envelope(new DoctrineInboxIntegrationTestEvent(), [new DispatchAfterCurrentBusStamp()]);
        $this->outbox->failures = 1;

        try {
            $this->bus->dispatch($this->createReceivedEnvelope());
            $this->fail('Storing the message should have failed.');
        } catch (HandlerFailedException) {
        }

        $this->assertSame(0, $this->countRows('orders'), 'the changes of the handler were rolled back with the inbox record');

        $this->bus->dispatch($this->createReceivedEnvelope());
        $this->bus->dispatch($this->createReceivedEnvelope());

        $this->assertSame(1, $this->countRows('orders'));
        $this->assertSame(1, $this->countRows('messenger_messages'));
    }

    public function testAMessageDispatchedAfterTheCurrentOneToATransportWithoutOutboxIsRefused()
    {
        $this->handler->dispatch = new Envelope(new DoctrineInboxIntegrationTestEvent(), [new DispatchAfterCurrentBusStamp(), new TransportNamesStamp(['invoices'])]);

        try {
            $this->bus->dispatch($this->createReceivedEnvelope());
            $this->fail('The handler should have failed.');
        } catch (HandlerFailedException $e) {
            $this->assertInstanceOf(LogicException::class, $e->getPrevious());
        }

        $this->assertSame(0, $this->countRows('orders'));
        $this->assertSame(0, $this->countRows('messenger_inbox'));
        $this->assertSame([], $this->invoices->getSent());
    }

    private function createReceivedEnvelope(): Envelope
    {
        return new Envelope(new DummyMessage('Hey'), [new ReceivedStamp('orders'), new MessageIdStamp('id-1')]);
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM $table");
    }
}

class DoctrineInboxIntegrationTestEvent
{
}

class DoctrineInboxIntegrationTestHandler
{
    public MessageBusInterface $bus;
    public ?Envelope $dispatch = null;
    public int $failures = 0;
    public int $calls = 0;

    public function __construct(
        private DBALConnection $connection,
    ) {
    }

    public function __invoke(DummyMessage $message): void
    {
        ++$this->calls;
        $this->connection->insert('orders', ['name' => $message->getMessage()]);

        if (null !== $this->dispatch) {
            $this->bus->dispatch($this->dispatch);
        }

        if (0 < $this->failures--) {
            throw new \RuntimeException('Failure.');
        }
    }
}

class DoctrineInboxIntegrationTestSender implements SenderInterface
{
    public int $failures = 0;

    public function __construct(
        private SenderInterface $sender,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        if (0 < $this->failures--) {
            throw new TransportException('Failure.');
        }

        return $this->sender->send($envelope);
    }
}

class DoctrineInboxIntegrationTestTransactionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private DBALConnection $connection,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $this->connection->beginTransaction();

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
            $this->connection->commit();

            return $envelope;
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }
}

class DoctrineInboxIntegrationTestLocator implements ContainerInterface
{
    use ServiceLocatorTrait;
}
