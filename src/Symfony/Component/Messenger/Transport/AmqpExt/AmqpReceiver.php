<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Transport\AmqpExt\Exception\RejectMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Symfony Messenger receiver to get messages from AMQP brokers using PHP's AMQP extension.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class AmqpReceiver implements ReceiverInterface
{
    private $serializer;
    private $connection;
    private $shouldStop;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? Serializer::create();
    }

    /**
     * {@inheritdoc}
     */
    public function receive(callable $handler): void
    {
        while (!$this->shouldStop) {
            $AMQPEnvelope = $this->connection->get();
            if (null === $AMQPEnvelope) {
                $handler(null);

                usleep($this->connection->getConnectionCredentials()['loop_sleep'] ?? 200000);
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                continue;
            }

            try {
                $handler($this->serializer->decode(array(
                    'body' => $AMQPEnvelope->getBody(),
                    'headers' => $AMQPEnvelope->getHeaders(),
                )));

                $this->connection->ack($AMQPEnvelope);
            } catch (RejectMessageExceptionInterface $e) {
                $this->connection->reject($AMQPEnvelope);

                throw $e;
            } catch (\Throwable $e) {
                $this->connection->nack($AMQPEnvelope, AMQP_REQUEUE);

                throw $e;
            } finally {
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            }
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }
}
