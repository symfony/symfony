<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Tests\Transport;

use Amp\Parallel\Context\ProcessContextFactory;
use Fabpot\Amp\Sqlite\SqliteConfig;
use Fabpot\Amp\Sqlite\SqliteConnectionPool;
use Fabpot\Amp\Sqlite\SqliteConnector;
use Fabpot\Amp\Sqlite\SqliteTransactionMode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmpSql\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\AmpSqlTransport;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\SqliteBackend;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

use function Amp\async;
use function Amp\delay;

final class AmpSqlTransportTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir().'/symfony-amp-sql-messenger-'.bin2hex(random_bytes(8)).'.db';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath.'-shm', $this->databasePath.'-wal'] as $file) {
            @unlink($file);
        }
    }

    public function testSendReceiveAcknowledgeAndReject()
    {
        $transport = $this->createTransport();

        try {
            $sent = $transport->send(new Envelope(new DummyMessage('first')));
            $transport->send(new Envelope(new DummyMessage('second')));

            self::assertIsInt($sent->last(TransportMessageIdStamp::class)?->getId());
            self::assertSame(2, $transport->getMessageCount());

            /** @var list<Envelope> $received */
            $received = iterator_to_array($transport->get(2));
            self::assertSame(['first', 'second'], array_map(static fn (Envelope $envelope) => self::messageValue($envelope), $received));
            self::assertSame(0, $transport->getMessageCount());

            $transport->ack($received[0]);
            $transport->reject($received[1]);

            self::assertSame([], iterator_to_array($transport->all()));
        } finally {
            $transport->close();
        }
    }

    public function testBinarySerializedBodyRoundTrips()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializedBody = "binary\0body\xff";
        $serializer->method('encode')->willReturn(['body' => $serializedBody]);
        $serializer->method('decode')->willReturnCallback(static function (array $encodedEnvelope) use ($serializedBody): Envelope {
            self::assertSame($serializedBody, $encodedEnvelope['body']);

            return new Envelope(new DummyMessage('binary'));
        });
        $transport = $this->createTransport(serializer: $serializer);

        try {
            $transport->send(new Envelope(new DummyMessage('binary')));
            $received = iterator_to_array($transport->get())[0];

            self::assertSame('binary', self::messageValue($received));
            $transport->ack($received);
        } finally {
            $transport->close();
        }
    }

    public function testNumericHeaderNameRoundTrips()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => 'body', 'headers' => ['0' => 'zero']]);
        $serializer->method('decode')->willReturnCallback(static function (array $encodedEnvelope): Envelope {
            self::assertSame(['0' => 'zero'], $encodedEnvelope['headers']);

            return new Envelope(new DummyMessage('numeric'));
        });
        $transport = $this->createTransport(serializer: $serializer);

        try {
            $transport->send(new Envelope(new DummyMessage('numeric')));
            $received = iterator_to_array($transport->get())[0];

            self::assertSame('numeric', self::messageValue($received));
            $transport->ack($received);
        } finally {
            $transport->close();
        }
    }

    public function testDelayedDelivery()
    {
        $transport = $this->createTransport();

        try {
            $transport->send(new Envelope(new DummyMessage('delayed'), [new DelayStamp(100)]));

            self::assertSame([], iterator_to_array($transport->get()));
            self::assertSame(0, $transport->getMessageCount());

            delay(0.15);
            /** @var list<Envelope> $received */
            $received = iterator_to_array($transport->get());
            self::assertSame('delayed', self::messageValue($received[0]));
            $transport->ack($received[0]);
        } finally {
            $transport->close();
        }
    }

    public function testRedeliveryAfterClaimTimeout()
    {
        $transport = $this->createTransport(['redeliver_timeout' => 0]);

        try {
            $transport->send(new Envelope(new DummyMessage('redelivered')));
            $first = iterator_to_array($transport->get())[0];
            delay(0.01);
            $second = iterator_to_array($transport->get())[0];

            self::assertSame($first->last(TransportMessageIdStamp::class)?->getId(), $second->last(TransportMessageIdStamp::class)?->getId());
            $transport->ack($second);
        } finally {
            $transport->close();
        }
    }

    public function testKeepaliveExtendsClaim()
    {
        $transport = $this->createTransport(['redeliver_timeout' => 4]);

        try {
            $transport->send(new Envelope(new DummyMessage('kept-alive')));
            $received = iterator_to_array($transport->get())[0];
            // the timings leave seconds of slack on each side: a loaded runner
            // that drifts must not push the keepalive past the initial claim,
            // nor the assertion below past the extended one
            delay(2.0);
            $transport->keepalive($received, 4);
            delay(2.5);

            self::assertSame([], iterator_to_array($transport->get()));
            $transport->ack($received);
        } finally {
            $transport->close();
        }
    }

    public function testListingIncludesOnlyCurrentlyAvailableMessages()
    {
        $transport = $this->createTransport();

        try {
            $transport->send(new Envelope(new DummyMessage('claimed')));
            $transport->send(new Envelope(new DummyMessage('delayed'), [new DelayStamp(60_000)]));
            $transport->send(new Envelope(new DummyMessage('available')));
            iterator_to_array($transport->get());

            /** @var list<Envelope> $messages */
            $messages = iterator_to_array($transport->all());
            self::assertSame(['available'], array_map(static fn (Envelope $envelope) => self::messageValue($envelope), $messages));
        } finally {
            $transport->close();
        }
    }

    public function testQueueIsolationAndFailureTransportOperations()
    {
        $async = $this->createTransport(['queue_name' => 'async']);
        $failed = $this->createTransport(['queue_name' => 'failed']);

        try {
            $async->send(new Envelope(new DummyMessage('async')));
            $failedMessage = $failed->send(new Envelope(new DummyMessage('failed')));
            $failedId = $failedMessage->last(TransportMessageIdStamp::class)?->getId();

            self::assertSame(1, $async->getMessageCount());
            self::assertSame(1, $failed->getMessageCount());
            $failedEnvelope = $failed->find($failedId);
            self::assertNotNull($failedEnvelope);
            self::assertSame('failed', self::messageValue($failedEnvelope));
            self::assertNull($async->find($failedId));
            /** @var list<Envelope> $failedEnvelopes */
            $failedEnvelopes = iterator_to_array($failed->all());
            self::assertSame(['failed'], array_map(static fn (Envelope $envelope) => self::messageValue($envelope), $failedEnvelopes));

            $failed->reject($failedEnvelope);
            self::assertSame(0, $failed->getMessageCount());
            self::assertSame(1, $async->getMessageCount());
        } finally {
            $async->close();
            $failed->close();
        }
    }

    public function testSetupIsIdempotent()
    {
        $first = $this->createTransport(['auto_setup' => false]);
        $second = $this->createTransport(['auto_setup' => false]);

        try {
            [$firstSetup, $secondSetup] = [
                async(static fn () => $first->setup()),
                async(static fn () => $second->setup()),
            ];
            $firstSetup->await();
            $secondSetup->await();
            $first->setup();
            $first->send(new Envelope(new DummyMessage('setup')));

            self::assertSame(1, $first->getMessageCount());
        } finally {
            $first->close();
            $second->close();
        }
    }

    public function testConcurrentConsumersClaimMessageOnlyOnce()
    {
        $sender = $this->createTransport();
        $firstConsumer = $this->createTransport();
        $secondConsumer = $this->createTransport();

        try {
            $sender->setup();
            $sender->send(new Envelope(new DummyMessage('once')));
            [$first, $second] = [
                async(static fn (): array => iterator_to_array($firstConsumer->get())),
                async(static fn (): array => iterator_to_array($secondConsumer->get())),
            ];
            $firstClaims = $first->await();
            $secondClaims = $second->await();
            if (!\is_array($firstClaims) || !\is_array($secondClaims)) {
                self::fail('Expected consumers to return arrays.');
            }
            $claims = [...$firstClaims, ...$secondClaims];

            self::assertCount(1, $claims);
            self::assertInstanceOf(Envelope::class, $claims[0]);
            $sender->ack($claims[0]);
            self::assertSame(0, $sender->getMessageCount());
        } finally {
            $sender->close();
            $firstConsumer->close();
            $secondConsumer->close();
        }
    }

    private static function messageValue(Envelope $envelope): string
    {
        $message = $envelope->getMessage();
        self::assertInstanceOf(DummyMessage::class, $message);

        return $message->getValue();
    }

    /**
     * @param array{auto_setup?: bool, queue_name?: string, redeliver_timeout?: int, table_name?: string} $options
     */
    private function createTransport(array $options = [], ?SerializerInterface $serializer = null): AmpSqlTransport
    {
        $options += [
            'auto_setup' => true,
            'queue_name' => 'default',
            'redeliver_timeout' => 3600,
            'table_name' => 'messenger_amp_messages',
        ];
        $config = (new SqliteConfig($this->databasePath))
            ->withBusyTimeout(1000)
            ->withTransactionMode(SqliteTransactionMode::Immediate);
        $connector = new SqliteConnector(new ProcessContextFactory(childConnectTimeout: 30));
        $pool = new SqliteConnectionPool($config, 2, connector: $connector, transactionIsolation: SqliteTransactionMode::Immediate);

        return new AmpSqlTransport(new Connection($pool, new SqliteBackend(), $options), $serializer ?? new PhpSerializer());
    }
}
