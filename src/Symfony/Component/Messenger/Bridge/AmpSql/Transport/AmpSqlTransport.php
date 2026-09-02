<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\CloseableTransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class AmpSqlTransport implements TransportInterface, KeepaliveReceiverInterface, SetupableTransportInterface, CloseableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    private AmpSqlReceiver $receiver;
    private AmpSqlSender $sender;

    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    public function get(int $fetchSize = 1): iterable
    {
        return $this->getReceiver()->get($fetchSize);
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
        $this->getReceiver()->keepalive($envelope, $seconds);
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

    public function all(?int $limit = null): iterable
    {
        return $this->getReceiver()->all($limit);
    }

    public function find(mixed $id): ?Envelope
    {
        return $this->getReceiver()->find($id);
    }

    public function close(): void
    {
        $this->connection->close();
    }

    private function getReceiver(): AmpSqlReceiver
    {
        return $this->receiver ??= new AmpSqlReceiver($this->connection, $this->serializer);
    }

    private function getSender(): AmpSqlSender
    {
        return $this->sender ??= new AmpSqlSender($this->connection, $this->serializer);
    }
}
