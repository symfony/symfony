<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Adapter\AmqpExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Adapter\AmqpExt\AmqpReceiver;
use Symfony\Component\Messenger\Adapter\AmqpExt\AmqpSender;
use Symfony\Component\Messenger\Adapter\AmqpExt\Connection;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Enhancers\MaximumCountReceiver;
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

        $sender = new AmqpSender($serializer, $connection);
        $receiver = new AmqpReceiver($serializer, $connection);

        $sender->send($firstMessage = new DummyMessage('First'));
        $sender->send($secondMessage = new DummyMessage('Second'));

        $receivedMessages = 0;
        $receiver->receive(function ($message) use ($receiver, &$receivedMessages, $firstMessage, $secondMessage) {
            $this->assertEquals(0 == $receivedMessages ? $firstMessage : $secondMessage, $message);

            if (2 == ++$receivedMessages) {
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

        $sender = new AmqpSender($serializer, $connection);
        $sender->send(new DummyMessage('Hello'));

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
        $this->assertEquals($expectedOutput."Get message: Symfony\Component\Messenger\Asynchronous\Transport\ReceivedMessage\nDone.\n", $process->getOutput());
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

        $sender = new AmqpSender($serializer, $connection);
        $receiver = new MaximumCountReceiver(new AmqpReceiver($serializer, $connection), 2);
        $receiver->receive(function ($message) {
            $this->assertNull($message);
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
