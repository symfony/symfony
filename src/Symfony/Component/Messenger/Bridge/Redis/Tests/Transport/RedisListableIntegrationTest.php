<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceiver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

#[RequiresPhpExtension('redis')]
#[Group('integration')]
class RedisListableIntegrationTest extends TestCase
{
    private ?Connection $connection = null;
    private ?\Redis $redis = null;
    private string $streamName;

    protected function setUp(): void
    {
        $this->streamName = 'test-stream-'.uniqid();
        $this->redis = new \Redis();

        try {
            $this->redis->connect('127.0.0.1', 6379);
            $this->redis->ping();
        } catch (\RedisException $e) {
            $this->markTestSkipped('Redis server is not available: '.$e->getMessage());
        }

        $this->connection = Connection::fromDsn('redis://127.0.0.1:6379/'.$this->streamName, [], $this->redis);
    }

    protected function tearDown(): void
    {
        if ($this->redis) {
            $this->redis->del($this->streamName);
            $this->redis->close();
        }
    }

    public function testAllReturnsAllMessages()
    {
        $receiver = $this->createReceiver();
        $this->assertInstanceOf(ListableReceiverInterface::class, $receiver);

        $this->addEnvelope(new Envelope(new DummyMessage('Hi')));
        $this->addEnvelope(new Envelope(new DummyMessage('Hello')));

        $envelopes = iterator_to_array($receiver->all());
        $this->assertCount(2, $envelopes);

        $this->assertEquals(new DummyMessage('Hi'), $envelopes[0]->getMessage());
        $this->assertEquals(new DummyMessage('Hello'), $envelopes[1]->getMessage());

        $this->assertNotNull($envelopes[0]->last(TransportMessageIdStamp::class));
        $this->assertNotNull($envelopes[0]->last(RedisReceivedStamp::class));
    }

    public function testAllWithLimit()
    {
        $this->addEnvelope(new Envelope(new DummyMessage('Hi')));
        $this->addEnvelope(new Envelope(new DummyMessage('Hello')));

        $envelopes = iterator_to_array($this->createReceiver()->all(1));
        $this->assertCount(1, $envelopes);
    }

    public function testFindReturnsMessageById()
    {
        $this->addEnvelope(new Envelope(new DummyMessage('Hi')));

        $messageId = $this->connection->findAll()[0]['id'];

        $foundEnvelope = $this->createReceiver()->find($messageId);
        $this->assertNotNull($foundEnvelope);
        $this->assertEquals(new DummyMessage('Hi'), $foundEnvelope->getMessage());
        $this->assertNotNull($foundEnvelope->last(TransportMessageIdStamp::class));
        $this->assertNotNull($foundEnvelope->last(RedisReceivedStamp::class));
    }

    public function testFindReturnsNullForNonExistentMessage()
    {
        $this->assertNull($this->createReceiver()->find('9999999999-0'));
    }

    private function createReceiver(): RedisReceiver
    {
        return new RedisReceiver($this->connection, $this->createSerializer());
    }

    private function createSerializer(): Serializer
    {
        return new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );
    }

    private function addEnvelope(Envelope $envelope): void
    {
        $encoded = $this->createSerializer()->encode($envelope);
        $this->connection->add($encoded['body'], $encoded['headers']);
    }
}
