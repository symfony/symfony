<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Transport;

use AsyncAws\Core\Exception\Http\HttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\CloseableTransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class AmazonSqsTransport implements TransportInterface, KeepaliveReceiverInterface, SetupableTransportInterface, CloseableTransportInterface, MessageCountAwareInterface, ResetInterface
{
    private SerializerInterface $serializer;

    public function __construct(
        private Connection $connection,
        ?SerializerInterface $serializer = null,
        private (ReceiverInterface&MessageCountAwareInterface)|null $receiver = null,
        private ?SenderInterface $sender = null,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        $receiver = $this->getReceiver();
        if ($receiver instanceof KeepaliveReceiverInterface) {
            $receiver->keepalive($envelope, $seconds);
        }
    }

    public function getMessageCount(): int
    {
        return $this->getReceiver()->getMessageCount();
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    public function setup(): void
    {
        try {
            $this->connection->setup();
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function reset(): void
    {
        try {
            $this->connection->reset();
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function close(): void
    {
        $this->reset();
    }

    private function getReceiver(): MessageCountAwareInterface&ReceiverInterface
    {
        return $this->receiver ??= new AmazonSqsReceiver($this->connection, $this->serializer);
    }

    private function getSender(): SenderInterface
    {
        return $this->sender ??= new AmazonSqsSender($this->connection, $this->serializer);
    }
}
