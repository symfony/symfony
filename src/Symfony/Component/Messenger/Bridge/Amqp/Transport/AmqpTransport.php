<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\CloseableTransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AmqpTransport implements QueueReceiverInterface, TransportInterface, SetupableTransportInterface, CloseableTransportInterface, MessageCountAwareInterface
{
    private SerializerInterface $serializer;
    private AmqpReceiver $receiver;
    private AmqpSender $sender;

    public function __construct(
        private Connection $connection,
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    public function getFromQueues(array $queueNames): iterable
    {
        return $this->getReceiver()->getFromQueues($queueNames);
    }

    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    public function setup(): void
    {
        $this->connection->setup();
    }

    public function getMessageCount(): int
    {
        return $this->getReceiver()->getMessageCount();
    }

    public function close(): void
    {
        $this->connection->clear();
    }

    private function getReceiver(): AmqpReceiver
    {
        return $this->receiver ??= new AmqpReceiver($this->connection, $this->serializer);
    }

    private function getSender(): AmqpSender
    {
        return $this->sender ??= new AmqpSender($this->connection, $this->serializer);
    }
}
