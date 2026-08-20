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

use MongoDB\Model\BSONDocument;
use Symfony\Component\Messenger\Bridge\MongoDb\Stamp\MongoDbReceivedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 */
class MongoDbReceiver implements MessageCountAwareInterface, ListableReceiverInterface
{
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
        $fetchSize = \func_num_args() > 0 ? max(1, func_get_arg(0)) : 1;

        $envelopes = [];
        while ($fetchSize-- > 0 && null !== $document = $this->connection->get()) {
            $envelopes[] = $this->createEnvelope($document);
        }

        return $envelopes;
    }

    public function ack(Envelope $envelope): void
    {
        $this->connection->ack($this->findReceivedStamp($envelope)->getId());
    }

    public function reject(Envelope $envelope): void
    {
        $this->connection->reject($this->findReceivedStamp($envelope)->getId());
    }

    /**
     * @return iterable<Envelope>
     */
    public function all(?int $limit = null): iterable
    {
        foreach ($this->connection->findAll($limit) as $document) {
            yield $this->createEnvelope($document);
        }
    }

    public function find(mixed $id): ?Envelope
    {
        $document = $this->connection->find((string) $id);

        if (!$document instanceof BSONDocument) {
            return null;
        }

        return $this->createEnvelope($document);
    }

    public function getMessageCount(): int
    {
        return $this->connection->getMessageCount();
    }

    private function findReceivedStamp(Envelope $envelope): MongoDbReceivedStamp
    {
        return $envelope->last(MongoDbReceivedStamp::class) ?? throw new LogicException('No MongoDbReceivedStamp found on the Envelope.');
    }

    private function createEnvelope(BSONDocument $document): Envelope
    {
        $documentId = (string) $document['_id'];

        if (($document['headers'] ?? null) instanceof \stdClass) {
            $headers = (array) $document['headers'];
        } else {
            $headers = null;
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $document['body'] ?? null,
                'headers' => $headers,
            ]);
        } catch (MessageDecodingFailedException $exception) {
            $this->connection->reject($documentId);

            throw $exception;
        }

        return $envelope->with(
            new MongoDbReceivedStamp($documentId),
            new TransportMessageIdStamp($documentId)
        );
    }
}
