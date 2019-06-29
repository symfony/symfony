<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Doctrine;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
class DoctrineTransport implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    private $connection;
    private $serializer;
    private $receiver;
    private $sender;

    public function __construct(Connection $connection, SerializerInterface $serializer)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        return ($this->receiver ?? $this->getReceiver())->get();
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        ($this->receiver ?? $this->getReceiver())->ack($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        ($this->receiver ?? $this->getReceiver())->reject($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageCount(): int
    {
        return ($this->receiver ?? $this->getReceiver())->getMessageCount();
    }

    /**
     * {@inheritdoc}
     */
    public function all(int $limit = null): iterable
    {
        return ($this->receiver ?? $this->getReceiver())->all($limit);
    }

    /**
     * {@inheritdoc}
     */
    public function find($id): ?Envelope
    {
        return ($this->receiver ?? $this->getReceiver())->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        return ($this->sender ?? $this->getSender())->send($envelope);
    }

    /**
     * {@inheritdoc}
     */
    public function setup(): void
    {
        $this->connection->setup();
    }

    private function getReceiver(): DoctrineReceiver
    {
        return $this->receiver = new DoctrineReceiver($this->connection, $this->serializer);
    }

    private function getSender(): DoctrineSender
    {
        return $this->sender = new DoctrineSender($this->connection, $this->serializer);
    }
}
