<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Tests\Transport;

use MongoDB\Client;
use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbSessionStamp;
use Symfony\Component\Messenger\Bridge\MongoDb\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
class MongoDbTransportIntegrationTest extends TestCase
{
    private const DATABASE = 'messenger_tests';

    private Client $client;
    private Connection $connection;
    private MongoDbTransport $transport;

    protected function setUp(): void
    {
        if (!class_exists(Client::class)) {
            $this->markTestSkipped('The "mongodb/mongodb" package is required.');
        }

        $clientClass = new \ReflectionClass(Client::class);
        if ($clientClass->isAbstract()) {
            self::fail(\sprintf('MongoDB\Client is shadowed by the test stub "%s".', $clientClass->getFileName()));
        }

        $this->client = new Client(getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', ['serverSelectionTimeoutMS' => 3000]);

        try {
            $this->client->getDatabase(self::DATABASE)->command(['ping' => 1]);
        } catch (\Throwable) {
            $this->markTestSkipped('MongoDB server not found.');
        }

        $this->connection = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, [], $this->client);
        $this->connection->deleteAll();
        $this->transport = new MongoDbTransport($this->connection, new PhpSerializer());
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->deleteAll();
        }
    }

    public function testSendGetAckRoundtrip()
    {
        $sentEnvelope = $this->transport->send(new Envelope(new DummyMessage('Hi')));
        $this->assertNotNull($sentEnvelope->last(TransportMessageIdStamp::class));
        $this->assertSame(1, $this->transport->getMessageCount());

        $envelopes = iterator_to_array($this->transport->get());
        $this->assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        $this->assertInstanceOf(DummyMessage::class, $message);
        $this->assertSame('Hi', $message->getMessage());

        // the message is locked for other consumers while it is handled
        $this->assertSame(0, iterator_count($this->transport->get()));

        $this->transport->ack($envelopes[0]);
        $this->assertSame(0, $this->transport->getMessageCount());
    }

    public function testReject()
    {
        $this->transport->send(new Envelope(new DummyMessage('Hi')));

        $envelopes = iterator_to_array($this->transport->get());
        $this->assertCount(1, $envelopes);

        $this->transport->reject($envelopes[0]);
        $this->assertSame(0, $this->transport->getMessageCount());
    }

    public function testSendWithDelay()
    {
        $this->transport->send(new Envelope(new DummyMessage('Later'), [new DelayStamp(60000)]));

        $this->assertSame(0, $this->transport->getMessageCount());
        $this->assertSame(0, iterator_count($this->transport->get()));
    }

    public function testMessageIsRedeliveredAfterTheRedeliverTimeout()
    {
        $this->transport->send(new Envelope(new DummyMessage('Hi')));

        $this->assertSame(1, iterator_count($this->transport->get()));
        $this->assertSame(0, iterator_count($this->transport->get()));

        $impatientConnection = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['redeliver_timeout' => 0], $this->client);
        $impatientTransport = new MongoDbTransport($impatientConnection, new PhpSerializer());

        usleep(2000);
        $this->assertSame(1, iterator_count($impatientTransport->get()));
    }

    public function testAllAndFind()
    {
        $this->transport->send(new Envelope(new DummyMessage('First')));
        $sentEnvelope = $this->transport->send(new Envelope(new DummyMessage('Second')));

        $envelopes = iterator_to_array($this->transport->all());
        $this->assertCount(2, $envelopes);
        $this->assertSame(['First', 'Second'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $envelopes));

        $this->assertSame(1, iterator_count($this->transport->all(1)));

        $foundEnvelope = $this->transport->find($sentEnvelope->last(TransportMessageIdStamp::class)->getId());
        $this->assertNotNull($foundEnvelope);
        $this->assertSame('Second', $foundEnvelope->getMessage()->getMessage());
    }

    public function testSendWithSession()
    {
        $envelope = new Envelope(new DummyMessage('Hi'), [new MongoDbSessionStamp($this->client->startSession())]);

        $this->transport->send($envelope);

        $this->assertSame(1, $this->transport->getMessageCount());
    }

    public function testSendBatch()
    {
        $inserts = $this->countInserts(function () use (&$envelopes) {
            $envelopes = $this->transport->sendBatch([
                'a' => new Envelope(new DummyMessage('First')),
                'b' => new Envelope(new DummyMessage('Later'), [new DelayStamp(60000)]),
                'c' => new Envelope(new DummyMessage('Second')),
            ]);
        });

        $this->assertSame(1, $inserts);
        $this->assertSame(['a', 'b', 'c'], array_keys($envelopes));
        $this->assertSame('Later', $this->transport->find($envelopes['b']->last(TransportMessageIdStamp::class)->getId())?->getMessage()->getMessage());
        $this->assertSame(2, $this->transport->getMessageCount());
        $this->assertSame(['First', 'Second'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), iterator_to_array($this->transport->all(), false)));
    }

    public function testSendBatchInsertsTheMessagesOfEachSessionTogether()
    {
        $session = $this->client->startSession();

        $inserts = $this->countInserts(fn () => $this->transport->sendBatch([
            new Envelope(new DummyMessage('a')),
            new Envelope(new DummyMessage('b'), [new MongoDbSessionStamp($session)]),
            new Envelope(new DummyMessage('c'), [new MongoDbSessionStamp($session)]),
            new Envelope(new DummyMessage('d')),
        ]));

        $this->assertSame(3, $inserts);
        $this->assertSame(['a', 'b', 'c', 'd'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), iterator_to_array($this->transport->all(), false)));
    }

    public function testSendBatchReportsTheMessagesTheServerRejected()
    {
        $connection = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['collection_name' => 'messenger_unique_bodies'], $this->client);
        $collection = $this->client->getCollection(self::DATABASE, 'messenger_unique_bodies');
        $collection->drop();
        $collection->createIndex(['body' => 1], ['unique' => true]);

        try {
            $transport = new MongoDbTransport($connection, new PhpSerializer());
            $transport->send($duplicate = new Envelope(new DummyMessage('b')));

            try {
                $transport->sendBatch(['a' => new Envelope(new DummyMessage('a')), 'b' => $duplicate, 'c' => new Envelope(new DummyMessage('c'))]);
                $this->fail('An exception should have been thrown.');
            } catch (BatchSendFailedException $e) {
                $this->assertSame(['a', 'c'], array_keys($e->getEnvelopes()));
                $this->assertSame(['b'], array_keys($e->getExceptions()));
                $this->assertInstanceOf(TransportException::class, $e->getExceptions()['b']);
                $this->assertStringContainsString('E11000', $e->getExceptions()['b']->getMessage());
                $this->assertSame('c', $transport->find($e->getEnvelopes()['c']->last(TransportMessageIdStamp::class)->getId())?->getMessage()->getMessage());
            }

            $this->assertSame(3, $connection->getMessageCount());
        } finally {
            $collection->drop();
        }
    }

    public function testGetFromQueuesClaimsAcrossSeveralQueuesWithASingleRequest()
    {
        $foo = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['queue_name' => 'foo'], $this->client);
        $bar = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['queue_name' => 'bar'], $this->client);
        $foo->deleteAll();
        $bar->deleteAll();

        try {
            $serializer = new PhpSerializer();
            (new MongoDbTransport($foo, $serializer))->send(new Envelope(new DummyMessage('from-foo')));
            usleep(2000);
            (new MongoDbTransport($bar, $serializer))->send(new Envelope(new DummyMessage('from-bar')));

            $this->assertSame(2, $this->client->getCollection(self::DATABASE, 'messenger_messages')
                ->countDocuments(['queueName' => ['$in' => ['foo', 'bar']]]));

            // the transport under test is bound to the "default" queue
            $envelopes = iterator_to_array($this->transport->getFromQueues(['foo', 'bar'], 2));

            $this->assertCount(2, $envelopes);
            $this->assertSame(
                ['from-foo', 'from-bar'],
                array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $envelopes)
            );

            foreach ($envelopes as $envelope) {
                $this->transport->ack($envelope);
            }
        } finally {
            $foo->deleteAll();
            $bar->deleteAll();
        }
    }

    public function testRetryIsSentBackToTheQueueTheMessageWasReceivedFrom()
    {
        $bar = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['queue_name' => 'bar'], $this->client);
        $bar->deleteAll();

        try {
            (new MongoDbTransport($bar, new PhpSerializer()))->send(new Envelope(new DummyMessage('from-bar')));

            $envelopes = iterator_to_array($this->transport->getFromQueues(['bar']));
            $this->assertCount(1, $envelopes);

            $this->transport->send($envelopes[0]->with(new RedeliveryStamp(1), new DelayStamp(100)));
            // the worker rejects the failed message right after the retry was sent
            $this->transport->reject($envelopes[0]);

            // exactly one document remains, in the queue the message was received from
            $documents = $this->client->getCollection(self::DATABASE, 'messenger_messages')
                ->find(['queueName' => 'bar'], ['typeMap' => ['root' => 'array', 'document' => 'array']])
                ->toArray();
            $this->assertCount(1, $documents);

            usleep(150_000);
            $retriedEnvelopes = iterator_to_array($this->transport->getFromQueues(['bar']));
            $this->assertCount(1, $retriedEnvelopes);
            $this->assertSame('from-bar', $retriedEnvelopes[0]->getMessage()->getMessage());
            $this->assertSame(1, RedeliveryStamp::getRetryCountFromEnvelope($retriedEnvelopes[0]));

            $this->transport->ack($retriedEnvelopes[0]);
        } finally {
            $bar->deleteAll();
        }
    }

    public function testRejectRemovesAMessageThatWasNeverClaimed()
    {
        $failed = Connection::fromDsn('mongodb://localhost/'.self::DATABASE, ['queue_name' => 'failed'], $this->client);
        $failed->deleteAll();

        try {
            // what a failure transport holds: documents inserted by send(), never claimed
            $failedTransport = new MongoDbTransport($failed, new PhpSerializer());
            $failedTransport->send(new Envelope(new DummyMessage('to-fail')));

            // what messenger:failed:remove and the skip of messenger:failed:retry do
            $envelopes = iterator_to_array($failedTransport->all(1));
            $this->assertCount(1, $envelopes);
            $failedTransport->reject($envelopes[0]);

            $this->assertSame(0, $failed->getMessageCount());
        } finally {
            $failed->deleteAll();
        }
    }

    public function testSetupCreatesTheIndex()
    {
        $this->transport->setup();

        $indexKeys = [];
        foreach ($this->client->getCollection(self::DATABASE, 'messenger_messages')->listIndexes() as $index) {
            $indexKeys[] = $index->getKey();
        }

        $this->assertContainsEquals(['availableAt' => 1, 'queueName' => 1, 'deliveredAt' => 1], $indexKeys);
    }

    private function countInserts(callable $callback): int
    {
        $subscriber = new class implements CommandSubscriber {
            public int $inserts = 0;

            public function commandStarted(CommandStartedEvent $event): void
            {
                if ('insert' === $event->getCommandName()) {
                    ++$this->inserts;
                }
            }

            public function commandSucceeded(CommandSucceededEvent $event): void
            {
            }

            public function commandFailed(CommandFailedEvent $event): void
            {
            }
        };

        $this->client->getManager()->addSubscriber($subscriber);

        try {
            $callback();
        } finally {
            $this->client->getManager()->removeSubscriber($subscriber);
        }

        return $subscriber->inserts;
    }
}
