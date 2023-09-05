<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TestsSymfony\Component\Messenger\Bridge\Kafka\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures\FakeMessage;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaTransportFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @requires extension rdkafka
 *
 * @group integration
 */
class KafkaIntegrationTest extends TestCase
{
    private const TOPIC_NAME = 'messenger_test';

    private string $dsn;
    private KafkaTransportFactory $factory;
    private SerializerInterface $serializer;
    private int $testIteration = 0;
    private \DateTimeInterface $testStartTime;

    protected function setUp(): void
    {
        parent::setUp();

        if (!getenv('MESSENGER_KAFKA_DSN')) {
            $this->markTestSkipped('The "MESSENGER_KAFKA_DSN" environment variable is required.');
        }

        $this->dsn = getenv('MESSENGER_KAFKA_DSN');
        $this->factory = new KafkaTransportFactory(new NullLogger());
        $this->serializer = $this->createMock(SerializerInterface::class);

        ++$this->testIteration;

        $this->testStartTime = $this->testStartTime ?? new \DateTimeImmutable();
    }

    public function testSendAndReceive()
    {
        $serializer = new Serializer();
        $topicName = $this->getTopicName('test_send_and_receive');

        $options = [
            'consumer' => [
                'topics' => [$topicName],
                'commit_async' => false,
                'receive_timeout' => 60000,
                'conf_options' => [
                    'group.id' => 'messenger_test'.$topicName,
                    'enable.auto.offset.store' => 'false',
                    'enable.auto.commit' => 'false',
                    'session.timeout.ms' => '10000',
                    'auto.offset.reset' => 'earliest',
                ],
            ],
            'producer' => [
                'topic' => $topicName,
                'flush_timeout' => 10000,
                'flush_retries' => 10,
                'conf_options' => [],
            ],
        ];

        $envelope = Envelope::wrap(new FakeMessage('Hello'), []);
        $receiver = $this->factory->createTransport($this->dsn, $options, $this->serializer);

        $this->serializer->expects(static::once())
            ->method('decode')
            ->willReturnCallback(
                function (array $encodedEnvelope) use ($serializer) {
                    $this->assertIsArray($encodedEnvelope);

                    $this->assertSame('{"message":"Hello"}', $encodedEnvelope['body']);

                    $this->assertArrayHasKey('headers', $encodedEnvelope);
                    $headers = $encodedEnvelope['headers'];

                    $this->assertSame(FakeMessage::class, $headers['type']);
                    $this->assertSame('application/json', $headers['Content-Type']);

                    return $serializer->decode($encodedEnvelope);
                }
            );

        $sender = $this->factory->createTransport($this->dsn, $options, $serializer);
        $sender->send($envelope);

        /** @var []Envelope $envelopes */
        $envelopes = $receiver->get();
        static::assertInstanceOf(Envelope::class, $envelopes[0]);

        $message = $envelopes[0]->getMessage();
        static::assertInstanceOf(FakeMessage::class, $message);

        $receiver->ack($envelopes[0]);
    }

    public function testReceiveFromTwoTopics()
    {
        $serializer = new Serializer();
        $topicName = $this->getTopicName('test_receive_from_two_topics');
        $topicNameA = $topicName.'_A';
        $topicNameB = $topicName.'_B';

        $senderA = $this->factory->createTransport(
            $this->dsn,
            [
                'conf' => [],
                'consumer' => [],
                'producer' => [
                    'topic' => $topicNameA,
                    'flush_timeout_ms' => 10000,
                    'poll_timeout_ms' => 0,
                    'conf' => [],
                ],
            ],
            $serializer
        );

        $senderB = $this->factory->createTransport(
            $this->dsn,
            [
                'conf' => [],
                'consumer' => [],
                'producer' => [
                    'topic' => $topicNameB,
                    'flush_timeout_ms' => 10000,
                    'poll_timeout_ms' => 0,
                    'conf' => [],
                ],
            ],
            $serializer
        );

        $senderA->send(Envelope::wrap(new FakeMessage('Hello_1'), []));
        $senderB->send(Envelope::wrap(new FakeMessage('Hello_2'), []));

        $receiver = $this->factory->createTransport(
            $this->dsn,
            [
                'conf' => [],
                'consumer' => [
                    'topics' => [$topicNameA, $topicNameB],
                    'commit_async' => false,
                    'receive_timeout_ms' => 60000,
                    'conf_options' => [
                        'group.id' => 'messenger_test_'.$topicName,
                        'enable.auto.offset.store' => 'false',
                        'enable.auto.commit' => 'false',
                        'session.timeout.ms' => '10000',
                        'auto.offset.reset' => 'earliest',
                    ],
                ],
                'producer' => [],
            ],
            $serializer
        );

        /** @var []Envelope $envelopes */
        $envelopes1 = $receiver->get();
        static::assertInstanceOf(FakeMessage::class, $envelopes1[0]->getMessage());
        $receiver->ack($envelopes1[0]);

        /** @var []Envelope $envelopes */
        $envelopes2 = $receiver->get();
        static::assertInstanceOf(FakeMessage::class, $envelopes2[0]->getMessage());
        $receiver->ack($envelopes2[0]);
    }

    private function getTopicName(string $name): string
    {
        return self::TOPIC_NAME.'_'.$this->testStartTime->getTimestamp().'_'.$this->testIteration.'_'.$name;
    }
}
