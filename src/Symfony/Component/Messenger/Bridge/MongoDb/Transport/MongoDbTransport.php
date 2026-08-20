<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 */
class MongoDbTransport implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    private MongoDbReceiver $receiver;
    private MongoDbSender $sender;

    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    /**
     * @param int $fetchSize
     *
     * @return Envelope[]
     */
    public function get(/* int $fetchSize = 1 */): iterable
    {
        return $this->getReceiver()->get(\func_num_args() > 0 ? func_get_arg(0) : 1);
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

    /**
     * @return iterable<Envelope>
     */
    public function all(?int $limit = null): iterable
    {
        return $this->getReceiver()->all($limit);
    }

    public function find(mixed $id): ?Envelope
    {
        return $this->getReceiver()->find($id);
    }

    public function getMessageCount(): int
    {
        return $this->getReceiver()->getMessageCount();
    }

    public function setup(): void
    {
        $this->connection->setup();
    }

    private function getReceiver(): MongoDbReceiver
    {
        return $this->receiver ??= new MongoDbReceiver($this->connection, $this->serializer);
    }

    private function getSender(): MongoDbSender
    {
        return $this->sender ??= new MongoDbSender($this->connection, $this->serializer);
    }
}
