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
class AmazonSqsTransport implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ResetInterface
{
    private SerializerInterface $serializer;
    private Connection $connection;
    private ?ReceiverInterface $receiver;
    private ?SenderInterface $sender;

    /**
     * @param MessageCountAwareInterface&ReceiverInterface|null $receiver
     */
    public function __construct(Connection $connection, SerializerInterface $serializer = null, ReceiverInterface $receiver = null, SenderInterface $sender = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
        $this->receiver = $receiver;
        $this->sender = $sender;
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

    /**
     * @return void
     */
    public function reset()
    {
        try {
            $this->connection->reset();
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @return MessageCountAwareInterface&ReceiverInterface
     */
    private function getReceiver(): ReceiverInterface
    {
        return $this->receiver ??= new AmazonSqsReceiver($this->connection, $this->serializer);
    }

    private function getSender(): SenderInterface
    {
        return $this->sender ??= new AmazonSqsSender($this->connection, $this->serializer);
    }
}
