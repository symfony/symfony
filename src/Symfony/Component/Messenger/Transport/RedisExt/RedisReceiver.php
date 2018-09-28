<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\RedisExt;

use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\RedisExt\Exception\RejectMessageExceptionInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class RedisReceiver implements ReceiverInterface
{
    private $connection;
    private $serializer;
    private $shouldStop = false;

    public function __construct(Connection $connection, SerializerInterface $serializer)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function receive(callable $handler): void
    {
        while (!$this->shouldStop) {
            if (null === $message = $this->connection->waitAndGet()) {
                $handler(null);
                if (\function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                continue;
            }

            try {
                $handler($this->serializer->decode($message));
                $this->connection->ack($message);
            } catch (RejectMessageExceptionInterface $e) {
                $this->connection->reject($message);

                throw $e;
            } catch (\Throwable $e) {
                $this->connection->requeue($message);

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
