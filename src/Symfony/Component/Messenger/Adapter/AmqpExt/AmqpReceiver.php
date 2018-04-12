<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Adapter\AmqpExt;

use Symfony\Component\Messenger\Adapter\AmqpExt\Exception\RejectMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;

/**
 * Symfony Messenger receiver to get messages from AMQP brokers using PHP's AMQP extension.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class AmqpReceiver implements ReceiverInterface
{
    private $messageDecoder;
    private $connection;
    private $shouldStop;

    public function __construct(DecoderInterface $messageDecoder, Connection $connection)
    {
        $this->messageDecoder = $messageDecoder;
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(callable $handler): void
    {
        while (!$this->shouldStop) {
            $message = $this->connection->get();
            if (null === $message) {
                $handler(null);

                usleep($this->connection->getConnectionCredentials()['loop_sleep'] ?? 200000);
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                continue;
            }

            try {
                $handler($this->messageDecoder->decode(array(
                    'body' => $message->getBody(),
                    'headers' => $message->getHeaders(),
                )));

                $this->connection->ack($message);
            } catch (RejectMessageExceptionInterface $e) {
                $this->connection->reject($message);

                throw $e;
            } catch (\Throwable $e) {
                $this->connection->nack($message, AMQP_REQUEUE);

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
