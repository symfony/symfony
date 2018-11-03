<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceiver;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpSender;
use Symfony\Component\Messenger\Transport\AmqpExt\Connection;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @requires extension amqp
 */
class AmqpExtIntegrationTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!getenv('MESSENGER_AMQP_DSN')) {
            $this->markTestSkipped('The "MESSENGER_AMQP_DSN" environment variable is required.');
        }
    }

    public function testItSendsAndReceivesMessages()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'));
        $connection->setup();
        $connection->queue()->purge();

        $sender = new AmqpSender($connection, $serializer);
        $receiver = new AmqpReceiver($connection, $serializer);

        $sender->send($first = new Envelope(new DummyMessage('First')));
        $sender->send($second = new Envelope(new DummyMessage('Second')));

        $receivedMessages = 0;
        $receiver->receive(function (?Envelope $envelope) use ($receiver, &$receivedMessages, $first, $second) {
            $this->assertEquals(0 == $receivedMessages ? $first : $second, $envelope);

            if (2 === ++$receivedMessages) {
                $receiver->stop();
            }
        });
    }

    public function testItReceivesSignals()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'));
        $connection->setup();
        $connection->queue()->purge();

        $sender = new AmqpSender($connection, $serializer);
        $sender->send(new Envelope(new DummyMessage('Hello')));

        $amqpReadTimeout = 30;
        $dsn = getenv('MESSENGER_AMQP_DSN').'?read_timeout='.$amqpReadTimeout;
        $process = new PhpProcess(file_get_contents(__DIR__.'/Fixtures/long_receiver.php'), null, array(
            'COMPONENT_ROOT' => __DIR__.'/../../../',
            'DSN' => $dsn,
        ));

        $process->start();

        $this->waitForOutput($process, $expectedOutput = "Receiving messages...\n");

        $signalTime = microtime(true);
        $timedOutTime = time() + 10;

        $process->signal(15);

        while ($process->isRunning() && time() < $timedOutTime) {
            usleep(100 * 1000); // 100ms
        }

        $this->assertFalse($process->isRunning());
        $this->assertLessThan($amqpReadTimeout, microtime(true) - $signalTime);
        $this->assertSame($expectedOutput.<<<'TXT'
Get envelope with message: Symfony\Component\Messenger\Tests\Fixtures\DummyMessage
with stamps: [
    "Symfony\\Component\\Messenger\\Stamp\\ReceivedStamp"
]
Done.

TXT
            , $process->getOutput());
    }

    /**
     * @runInSeparateProcess
     */
    public function testItSupportsTimeoutAndTicksNullMessagesToTheHandler()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $connection = Connection::fromDsn(getenv('MESSENGER_AMQP_DSN'), array('read_timeout' => '1'));
        $connection->setup();
        $connection->queue()->purge();

        $receiver = new AmqpReceiver($connection, $serializer);

        $receivedMessages = 0;
        $receiver->receive(function (?Envelope $envelope) use ($receiver, &$receivedMessages) {
            $this->assertNull($envelope);

            if (2 === ++$receivedMessages) {
                $receiver->stop();
            }
        });
    }

    private function waitForOutput(Process $process, string $output, $timeoutInSeconds = 10)
    {
        $timedOutTime = time() + $timeoutInSeconds;

        while (time() < $timedOutTime) {
            if (0 === strpos($process->getOutput(), $output)) {
                return;
            }

            usleep(100 * 1000); // 100ms
        }

        throw new \RuntimeException('Expected output never arrived. Got "'.$process->getOutput().'" instead.');
    }
}
