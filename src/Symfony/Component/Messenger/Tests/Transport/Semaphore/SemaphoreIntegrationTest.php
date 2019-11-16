<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Semaphore;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Semaphore\Connection;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreReceiver;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreSender;
use Symfony\Component\Messenger\Transport\Semaphore\SemaphoreStamp;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @author Cedrick Oka Baidai <okacedrick@gmail.com>
 */
class SemaphoreIntegrationTest extends TestCase
{
    /**
     * @var \Symfony\Component\Messenger\Transport\Semaphore\Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('MESSENGER_SEMAPHORE_DSN') ?: 'semaphore://'.__FILE__;
        $this->connection = Connection::fromDsn($dsn);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->connection->close();
    }

    public function testConnectionSendAndGet()
    {
        $this->connection->send('{"message": "Hi"}', ['type' => DummyMessage::class]);
        $message = $this->connection->get();

        $this->assertEquals('{"message": "Hi"}', $message->getBody());
        $this->assertEquals(['type' => DummyMessage::class], $message->getHeaders());
    }

    public function testItSendsAndReceivesMessages()
    {
        $serializer = $this->createSerializer();

        $sender = new SemaphoreSender($this->connection, $serializer);
        $receiver = new SemaphoreReceiver($this->connection, $serializer);

        $sender->send($first = new Envelope(new DummyMessage('First')));
        $sender->send($second = new Envelope(new DummyMessage('Second')));

        $envelopes = iterator_to_array($receiver->get());

        $this->assertCount(1, $envelopes);

        /** @var \Symfony\Component\Messenger\Envelope $envelope */
        $envelope = $envelopes[0];

        $this->assertEquals($first->getMessage(), $envelope->getMessage());
        $this->assertInstanceOf(SemaphoreStamp::class, $envelope->last(SemaphoreStamp::class));

        $envelopes = iterator_to_array($receiver->get());

        $this->assertCount(1, $envelopes);

        /** @var \Symfony\Component\Messenger\Envelope $envelope */
        $envelope = $envelopes[0];

        $this->assertEquals($second->getMessage(), $envelope->getMessage());
        $this->assertInstanceOf(SemaphoreStamp::class, $envelope->last(SemaphoreStamp::class));

        $this->assertEmpty(iterator_to_array($receiver->get()));
    }

    public function testItCountMessages()
    {
        $serializer = $this->createSerializer();

        $this->connection->close();
        $this->connection->setup();

        $sender = new SemaphoreSender($this->connection, $serializer);

        $sender->send(new Envelope(new DummyMessage('First')));
        $sender->send(new Envelope(new DummyMessage('Second')));
        $sender->send(new Envelope(new DummyMessage('Third')));

        $this->assertSame(3, $this->connection->getMessageCount());
    }

    private function createSerializer(): SerializerInterface
    {
        return new Serializer(
                new SerializerComponent\Serializer([new ObjectNormalizer(), new ArrayDenormalizer()], ['json' => new JsonEncoder()])
        );
    }
}
