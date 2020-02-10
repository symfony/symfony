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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class AmazonSqsTransport implements TransportInterface, SetupableTransportInterface, ResetInterface
{
    private $serializer;
    private $connection;
    private $receiver;
    private $sender;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
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

    public function reset()
    {
        $this->connection->reset();
    }

    private function getReceiver(): AmazonSqsReceiver
    {
        return $this->receiver = new AmazonSqsReceiver($this->connection, $this->serializer);
    }

    private function getSender(): AmazonSqsSender
    {
        return $this->sender = new AmazonSqsSender($this->connection, $this->serializer);
    }
}
