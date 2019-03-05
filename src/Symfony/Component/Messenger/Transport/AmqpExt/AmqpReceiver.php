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

use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\AmqpExt\Exception\RecoverableMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\AmqpExt\Exception\UnrecoverableMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Symfony Messenger receiver to get messages from AMQP brokers using PHP's AMQP extension.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 */
class AmqpReceiver implements ReceiverInterface
{
    private $serializer;
    private $connection;
    private $shouldStop;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
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
                $handler($this->serializer->decode([
                    'body' => $AMQPEnvelope->getBody(),
                    'headers' => $AMQPEnvelope->getHeaders(),
                ]));

                $this->connection->ack($AMQPEnvelope);
            } catch (RecoverableMessageExceptionInterface $e) {
                try {
                    $this->connection->nack($AMQPEnvelope, AMQP_REQUEUE);
                } catch (\AMQPException $exception) {
                    throw new TransportException($exception->getMessage(), 0, $exception);
                }
            } catch (UnrecoverableMessageExceptionInterface $e) {
                try {
                    $this->connection->nack($AMQPEnvelope, AMQP_NOPARAM);
                } catch (\AMQPException $exception) {
                    throw new TransportException($exception->getMessage(), 0, $exception);
                }
            } catch (\AMQPException $e) {
                throw new TransportException($e->getMessage(), 0, $e);
            } catch (\Throwable $e) {
                $connectionCredentials = $this->connection->getConnectionCredentials() + [
                        'consume_fatal' => true,
                        'consume_requeue' => false,
                ];
                $flag = $connectionCredentials['consume_requeue'] ? AMQP_REQUEUE : AMQP_NOPARAM;
                try {
                    $this->connection->nack($AMQPEnvelope, $flag);
                } catch (\AMQPException $exception) {
                    throw new TransportException($exception->getMessage(), 0, $exception);
                }
                if ($connectionCredentials['consume_fatal']) {
                    throw $e;
                }
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
