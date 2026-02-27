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
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class AmazonSqsReceiver implements KeepaliveReceiverInterface, MessageCountAwareInterface
{
    private SerializerInterface $serializer;

    public function __construct(
        private Connection $connection,
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    public function get(): iterable
    {
        try {
            if (!$sqsEnvelope = $this->connection->get()) {
                return;
            }
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        $stamps = [
            new AmazonSqsReceivedStamp($sqsEnvelope['id']),
            new TransportMessageIdStamp($sqsEnvelope['id']),
        ];

        try {
            yield $this->serializer->decode($sqsEnvelope = [
                'body' => $sqsEnvelope['body'],
                'headers' => $sqsEnvelope['headers'],
            ])->with(...$stamps);
        } catch (MessageDecodingFailedException $e) {
            yield MessageDecodingFailedException::wrap($sqsEnvelope, $e->getMessage(), $e->getCode(), $e)->with(...$stamps);
        }
    }

    public function ack(Envelope $envelope): void
    {
        try {
            $this->connection->delete($this->findSqsReceivedStampId($envelope));
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function reject(Envelope $envelope): void
    {
        try {
            $this->connection->reject($this->findSqsReceivedStampId($envelope));
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        try {
            $this->connection->keepalive($this->findSqsReceivedStampId($envelope), $seconds);
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function getMessageCount(): int
    {
        try {
            return $this->connection->getMessageCount();
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    private function findSqsReceivedStampId(Envelope $envelope): string
    {
        return $envelope->last(AmazonSqsReceivedStamp::class)?->getId() ?? throw new LogicException('No AmazonSqsReceivedStamp found on the Envelope.');
    }
}
