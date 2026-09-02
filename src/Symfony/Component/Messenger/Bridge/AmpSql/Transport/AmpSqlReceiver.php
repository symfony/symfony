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
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class AmpSqlReceiver implements ListableReceiverInterface, MessageCountAwareInterface, KeepaliveReceiverInterface
{
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer,
    ) {
    }

    public function get(int $fetchSize = 1): iterable
    {
        $fetchSize = max(1, $fetchSize);

        foreach ($this->connection->get($fetchSize) as $data) {
            yield $this->createEnvelopeFromData($data);
        }
    }

    public function ack(Envelope $envelope): void
    {
        $this->connection->ack($this->findReceivedStampId($envelope));
    }

    public function reject(Envelope $envelope): void
    {
        $this->connection->reject($this->findReceivedStampId($envelope));
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        $this->connection->keepalive($this->findReceivedStampId($envelope), $seconds);
    }

    public function getMessageCount(): int
    {
        return $this->connection->getMessageCount();
    }

    public function all(?int $limit = null): iterable
    {
        foreach ($this->connection->findAll($limit) as $data) {
            yield $this->createEnvelopeFromData($data);
        }
    }

    public function find(mixed $id): ?Envelope
    {
        $data = $this->connection->find($id);

        return null === $data ? null : $this->createEnvelopeFromData($data);
    }

    private function findReceivedStampId(Envelope $envelope): int|string
    {
        return $envelope->last(AmpSqlReceivedStamp::class)?->getId() ?? throw new LogicException('No AmpSqlReceivedStamp found on the Envelope.');
    }

    /**
     * @param array{id: int|string, body: string, headers: array<string, string>} $data
     */
    private function createEnvelopeFromData(array $data): Envelope
    {
        $stamps = [
            new AmpSqlReceivedStamp($data['id']),
            new TransportMessageIdStamp($data['id']),
        ];
        $encodedEnvelope = ['body' => $data['body'], 'headers' => $data['headers']];

        try {
            return $this->serializer->decode($encodedEnvelope)->withoutAll(TransportMessageIdStamp::class)->with(...$stamps);
        } catch (MessageDecodingFailedException $e) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, $e->getMessage(), $e->getCode(), $e)->with(...$stamps);
        }
    }
}
