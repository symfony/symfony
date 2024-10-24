<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceiver;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpSender;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @requires extension amqp
 *
 * @group integration
 */
class AmqpExtIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!getenv('MESSENGER_AMQP_DSN')) {
            $this->markTestSkipped('The "MESSENGER_AMQP_DSN" environment variable is required.');
        }
    }

    public function testItSendsAndReceivesMessages()
    {
        $serializer = $this->createSerializer();

        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'));
        $connection->setup();
        $connection->purgeQueues();

        $sender = new AmqpSender($connection, $serializer);
        $receiver = new AmqpReceiver($connection, $serializer);

        $sender->send($first = new Envelope(new DummyMessage('First')));
        $sender->send($second = new Envelope(new DummyMessage('Second')));

        $envelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $envelopes);
        /** @var Envelope $envelope */
        $envelope = $envelopes[0];
        $this->assertEquals($first->getMessage(), $envelope->getMessage());
        $this->assertInstanceOf(AmqpReceivedStamp::class, $envelope->last(AmqpReceivedStamp::class));

        $envelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $envelopes);
        /** @var Envelope $envelope */
        $envelope = $envelopes[0];
        $this->assertEquals($second->getMessage(), $envelope->getMessage());

        $this->assertEmpty(iterator_to_array($receiver->get()));
    }

    public function testItSendsAndReceivesMessagesThroughDefaultExchange()
    {
        $serializer = $this->createSerializer();

        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'), ['exchange' => ['name' => '']]);
        $connection->setup();
        $connection->purgeQueues();

        $sender = new AmqpSender($connection, $serializer);
        $receiver = new AmqpReceiver($connection, $serializer);

        $sender->send($first = new Envelope(new DummyMessage('First'), [new AmqpStamp('messages')]));
        $sender->send($second = new Envelope(new DummyMessage('Second'), [new AmqpStamp('messages')]));

        $envelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $envelopes);
        /** @var Envelope $envelope */
        $envelope = $envelopes[0];
        $this->assertEquals($first->getMessage(), $envelope->getMessage());
        $this->assertInstanceOf(AmqpReceivedStamp::class, $envelope->last(AmqpReceivedStamp::class));

        $envelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $envelopes);
        /** @var Envelope $envelope */
        $envelope = $envelopes[0];
        $this->assertEquals($second->getMessage(), $envelope->getMessage());

        $this->assertEmpty(iterator_to_array($receiver->get()));
    }

    public function testRetryAndDelay()
    {
        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'));
        $connection->setup();
        $connection->purgeQueues();

        $sender = new AmqpSender($connection);
        $receiver = new AmqpReceiver($connection);

        // send a first message
        $sender->send($first = new Envelope(new DummyMessage('First')));

        // receive it immediately and imitate a redeliver with 2 second delay
        $envelopes = iterator_to_array($receiver->get());
        /** @var Envelope $envelope */
        $envelope = $envelopes[0];
        $newEnvelope = $envelope
            ->with(new DelayStamp(2000))
            ->with(new RedeliveryStamp(1));
        $sender->send($newEnvelope);
        $receiver->ack($envelope);

        // send a 2nd message with a shorter delay and custom routing key
        $customRoutingKeyMessage = new DummyMessage('custom routing key');
        $envelopeCustomRoutingKey = new Envelope($customRoutingKeyMessage, [
            new DelayStamp(1000),
            new AmqpStamp('my_custom_routing_key'),
        ]);
        $sender->send($envelopeCustomRoutingKey);

        // wait for next message (but max at 3 seconds)
        $startTime = microtime(true);
        $envelopes = $this->receiveEnvelopes($receiver, 3);

        // duration should be about 1 second
        $this->assertApproximateDuration($startTime, 1);

        // this should be the custom routing key message first
        $this->assertCount(1, $envelopes);
        /* @var Envelope $envelope */
        $receiver->ack($envelopes[0]);
        $this->assertEquals($customRoutingKeyMessage, $envelopes[0]->getMessage());

        // wait for final message (but max at 3 seconds)
        $envelopes = $this->receiveEnvelopes($receiver, 3);
        // duration should be about 2 seconds
        $this->assertApproximateDuration($startTime, 2);

        /* @var RedeliveryStamp|null $retryStamp */
        // verify the stamp still exists from the last send
        $this->assertCount(1, $envelopes);
        $retryStamp = $envelopes[0]->last(RedeliveryStamp::class);
        $this->assertNotNull($retryStamp);
        $this->assertSame(1, $retryStamp->getRetryCount());

        $receiver->ack($envelope);
    }

    public function testRetryAffectsOnlyOriginalQueue()
    {
        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'), [
            'exchange' => [
                'name' => 'messages_topic',
                'type' => 'topic',
                'default_publish_routing_key' => 'topic_routing_key',
            ],
            'queues' => [
                'A' => ['binding_keys' => ['topic_routing_key']],
                'B' => ['binding_keys' => ['topic_routing_key']],
            ],
        ]);
        $connection->setup();
        $connection->purgeQueues();

        $sender = new AmqpSender($connection);
        $receiver = new AmqpReceiver($connection);

        // initial delivery: should receive in both queues
        $sender->send(new Envelope(new DummyMessage('Payload')));

        $receivedEnvelopes = $this->receiveWithQueueName($receiver);
        $this->assertCount(2, $receivedEnvelopes);
        $this->assertArrayHasKey('A', $receivedEnvelopes);
        $this->assertArrayHasKey('B', $receivedEnvelopes);

        // retry: should receive in only "A" queue
        $retryEnvelope = $receivedEnvelopes['A']
            ->with(new DelayStamp(10))
            ->with(new RedeliveryStamp(1));
        $sender->send($retryEnvelope);

        $retriedEnvelopes = $this->receiveWithQueueName($receiver);
        $this->assertCount(1, $retriedEnvelopes);
        $this->assertArrayHasKey('A', $retriedEnvelopes);
    }

    public function testItReceivesSignals()
    {
        $serializer = $this->createSerializer();

        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'));
        $connection->setup();
        $connection->purgeQueues();

        $sender = new AmqpSender($connection, $serializer);
        $sender->send(new Envelope(new DummyMessage('Hello')));

        $amqpReadTimeout = 30;
        $dsn = getenv('MESSENGER_AMQP_DSN').'?read_timeout='.$amqpReadTimeout;
        $process = new PhpProcess(file_get_contents(__DIR__.'/../Fixtures/long_receiver.php'), null, [
            'COMPONENT_ROOT' => __DIR__.'/../../',
            'DSN' => $dsn,
        ]);

        $process->start();

        $this->waitForOutput($process, $expectedOutput = "Receiving messages...\n");

        $signalTime = microtime(true);
        $timedOutTime = time() + 10;

        // wait for worker started and registered the signal handler
        usleep(100 * 1000); // 100ms

        // immediately after the process has started "booted", kill it
        $process->signal(15);

        while ($process->isRunning() && time() < $timedOutTime) {
            usleep(100 * 1000); // 100ms
        }

        // make sure the process exited, after consuming only the 1 message
        $this->assertFalse($process->isRunning());
        $this->assertLessThan($amqpReadTimeout, microtime(true) - $signalTime);
        $this->assertSame($expectedOutput.<<<'TXT'
Get envelope with message: Symfony\Component\Messenger\Bridge\Amqp\Tests\Fixtures\DummyMessage
with stamps: [
    "Symfony\\Component\\Messenger\\Stamp\\SerializedMessageStamp",
    "Symfony\\Component\\Messenger\\Bridge\\Amqp\\Transport\\AmqpReceivedStamp",
    "Symfony\\Component\\Messenger\\Stamp\\ReceivedStamp",
    "Symfony\\Component\\Messenger\\Stamp\\ConsumedByWorkerStamp",
    "Symfony\\Component\\Messenger\\Stamp\\AckStamp"
]
Done.

TXT
            , $process->getOutput());
    }

    public function testItCountsMessagesInQueue()
    {
        $serializer = $this->createSerializer();

        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'));
        $connection->setup();
        $connection->purgeQueues();

        $sender = new AmqpSender($connection, $serializer);

        $sender->send(new Envelope(new DummyMessage('First')));
        $sender->send(new Envelope(new DummyMessage('Second')));
        $sender->send(new Envelope(new DummyMessage('Third')));

        sleep(1); // give amqp a moment to have the messages ready
        $this->assertSame(3, $connection->countMessagesInQueues());
    }

    private function waitForOutput(Process $process, string $output, $timeoutInSeconds = 10)
    {
        $timedOutTime = time() + $timeoutInSeconds;

        while (time() < $timedOutTime) {
            if (str_starts_with($process->getOutput(), $output)) {
                return;
            }

            usleep(100 * 1000); // 100ms
        }

        throw new \RuntimeException('Expected output never arrived. Got "'.$process->getOutput().'" instead.');
    }

    private function createSerializer(): SerializerInterface
    {
        return new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer(), new ArrayDenormalizer()], ['json' => new JsonEncoder()])
        );
    }

    private function assertApproximateDuration($startTime, int $expectedDuration)
    {
        $actualDuration = microtime(true) - $startTime;

        if (method_exists($this, 'assertEqualsWithDelta')) {
            $this->assertEqualsWithDelta($expectedDuration, $actualDuration, .5, 'Duration was not within expected range');
        } else {
            $this->assertEquals($expectedDuration, $actualDuration, 'Duration was not within expected range', .5);
        }
    }

    /**
     * @return Envelope[]
     */
    private function receiveEnvelopes(ReceiverInterface $receiver, int $timeout): array
    {
        $envelopes = [];
        $startTime = microtime(true);
        while (0 === \count($envelopes) && $startTime + $timeout > time()) {
            $envelopes = iterator_to_array($receiver->get());
        }

        return $envelopes;
    }

    private function receiveWithQueueName(AmqpReceiver $receiver)
    {
        // let RabbitMQ receive messages
        usleep(100 * 1000); // 100ms

        $receivedEnvelopes = [];
        foreach ($receiver->get() as $envelope) {
            $queueName = $envelope->last(AmqpReceivedStamp::class)->getQueueName();
            $receivedEnvelopes[$queueName] = $envelope;
            $receiver->ack($envelope);
        }

        return $receivedEnvelopes;
    }
}
