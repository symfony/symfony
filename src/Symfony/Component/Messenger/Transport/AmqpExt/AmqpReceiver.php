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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Transport\AmqpExt\Exception\RejectMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;

/**
 * Symfony Messenger receiver to get messages from AMQP brokers using PHP's AMQP extension.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class AmqpReceiver implements ReceiverInterface
{
    private const DEFAULT_LOOP_SLEEP_IN_MICRO_SECONDS = 200000;

    private $decoder;
    private $connection;
    private $logger;
    private $shouldStop;

    public function __construct(DecoderInterface $decoder, Connection $connection, LoggerInterface $logger = null)
    {
        $this->decoder = $decoder;
        $this->connection = $connection;
        $this->logger = $logger;
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

                usleep($this->connection->getConnectionConfiguration()['loop_sleep'] ?? self::DEFAULT_LOOP_SLEEP_IN_MICRO_SECONDS);
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                continue;
            }

            try {
                $handler($this->decoder->decode(array(
                    'body' => $AMQPEnvelope->getBody(),
                    'headers' => $AMQPEnvelope->getHeaders(),
                )));

                $this->connection->ack($AMQPEnvelope);
            } catch (RejectMessageExceptionInterface $e) {
                $this->connection->reject($AMQPEnvelope);

                throw $e;
            } catch (\Throwable $e) {
                try {
                    $retried = $this->connection->publishForRetry($AMQPEnvelope);
                } catch (\Throwable $retryException) {
                    $this->logger && $this->logger->warning(sprintf('Retrying message #%s failed. Requeuing it now.', $AMQPEnvelope->getMessageId()), array(
                        'retryException' => $retryException,
                        'exception' => $e,
                    ));

                    $retried = false;
                }

                if (!$retried) {
                    $this->connection->nack($AMQPEnvelope, AMQP_REQUEUE);

                    throw $e;
                }

                // Acknowledge current message as another one as been requeued.
                $this->connection->ack($AMQPEnvelope);
            } finally {
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }
}
