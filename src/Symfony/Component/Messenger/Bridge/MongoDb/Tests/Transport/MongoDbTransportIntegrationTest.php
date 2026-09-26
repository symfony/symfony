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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbSessionStamp;
use Symfony\Component\Messenger\Bridge\MongoDb\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\Connection;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
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
    private bool $isReplicaSet = false;

    /** Cached across tests: the readiness probe and the replica set detection run once per process. */
    private static ?bool $serverReachable = null;
    private static bool $replicaSet = false;

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

        if (null === self::$serverReachable) {
            try {
                $this->client->getDatabase(self::DATABASE)->command(['ping' => 1]);
                $hello = $this->client->getDatabase('admin')->command(['hello' => 1])->toArray()[0];
                self::$serverReachable = true;
                self::$replicaSet = isset($hello['setName']) || isset($hello->setName);
            } catch (\Throwable) {
                self::$serverReachable = false;
            }
        }

        $this->isReplicaSet = self::$replicaSet;

        if (!self::$serverReachable) {
            if (false !== getenv('MONGODB_URI')) {
                self::fail('MongoDB server not found although MONGODB_URI was provided.');
            }

            $this->markTestSkipped('MongoDB server not found.');
        }

        // the existing tests assert polling semantics: disable the change stream
        // to keep them fast and deterministic (the stream path is covered by
        // dedicated tests using explicitly stream-enabled connections)
        $this->connection = Connection::fromDsn(getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', ['wait_time' => 0, 'database' => self::DATABASE], $this->client);
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

        $envelopes = $this->transport->get();
        $this->assertCount(1, $envelopes);
        $message = $envelopes[0]->getMessage();
        $this->assertInstanceOf(DummyMessage::class, $message);
        $this->assertSame('Hi', $message->getMessage());

        // the message is locked for other consumers while it is handled
        $this->assertSame([], $this->transport->get());

        $this->transport->ack($envelopes[0]);
        $this->assertSame(0, $this->transport->getMessageCount());
    }

    public function testReject()
    {
        $this->transport->send(new Envelope(new DummyMessage('Hi')));

        $envelopes = $this->transport->get();
        $this->assertCount(1, $envelopes);

        $this->transport->reject($envelopes[0]);
        $this->assertSame(0, $this->transport->getMessageCount());
    }

    public function testSendWithDelay()
    {
        $this->transport->send(new Envelope(new DummyMessage('Later'), [new DelayStamp(60000)]));

        $this->assertSame(0, $this->transport->getMessageCount());
        $this->assertSame([], $this->transport->get());
    }

    public function testMessageIsRedeliveredAfterTheRedeliverTimeout()
    {
        $this->transport->send(new Envelope(new DummyMessage('Hi')));

        $this->assertCount(1, $this->transport->get());
        $this->assertSame([], $this->transport->get());

        $impatientConnection = Connection::fromDsn(getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', ['redeliver_timeout' => 0, 'database' => self::DATABASE], $this->client);
        $impatientTransport = new MongoDbTransport($impatientConnection, new PhpSerializer());

        usleep(2000);
        $this->assertCount(1, $impatientTransport->get());
    }

    public function testAllAndFind()
    {
        $this->transport->send(new Envelope(new DummyMessage('First')));
        $sentEnvelope = $this->transport->send(new Envelope(new DummyMessage('Second')));

        $envelopes = iterator_to_array($this->transport->all());
        $this->assertCount(2, $envelopes);
        $this->assertSame(['First', 'Second'], array_map(static fn (Envelope $envelope) => $envelope->getMessage()->getMessage(), $envelopes));

        $this->assertCount(1, iterator_to_array($this->transport->all(1)));

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

    public function testSetupCreatesTheIndex()
    {
        $this->transport->setup();

        $indexKeys = [];
        foreach ($this->client->getCollection(self::DATABASE, 'messenger_messages')->listIndexes() as $index) {
            $indexKeys[] = $index->getKey();
        }

        $this->assertContainsEquals(['availableAt' => 1, 'queueName' => 1, 'deliveredAt' => 1], $indexKeys);
    }

    public function testChangeStreamsFallBackToPollingOnAStandaloneServer()
    {
        if ($this->isReplicaSet) {
            $this->markTestSkipped('A standalone server is required for this test.');
        }

        // nothing is claimable at first, so getFromStream() must attempt watch()
        // and fall back to polling on a standalone server
        $streaming = new MongoDbTransport(Connection::fromDsn(getenv('MONGODB_URI') ?: 'mongodb://localhost:27017', ['wait_time' => 1, 'database' => self::DATABASE], $this->client), new PhpSerializer());
        $this->assertSame([], $streaming->get());

        $streaming->send(new Envelope(new DummyMessage('Hi')));
        $envelopes = $streaming->get();
        $this->assertCount(1, $envelopes);
        $this->assertSame('Hi', $envelopes[0]->getMessage()->getMessage());
    }
}
