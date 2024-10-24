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
            $sqsEnvelope = $this->connection->get();
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
        if (null === $sqsEnvelope) {
            return;
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $sqsEnvelope['body'],
                'headers' => $sqsEnvelope['headers'],
            ]);
        } catch (MessageDecodingFailedException $exception) {
            $this->connection->delete($sqsEnvelope['id']);

            throw $exception;
        }

        yield $envelope->with(new AmazonSqsReceivedStamp($sqsEnvelope['id']));
    }

    public function ack(Envelope $envelope): void
    {
        try {
            $this->connection->delete($this->findSqsReceivedStamp($envelope)->getId());
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function reject(Envelope $envelope): void
    {
        try {
            $this->connection->delete($this->findSqsReceivedStamp($envelope)->getId());
        } catch (HttpException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        try {
            $this->connection->keepalive($this->findSqsReceivedStamp($envelope)->getId(), $seconds);
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

    private function findSqsReceivedStamp(Envelope $envelope): AmazonSqsReceivedStamp
    {
        /** @var AmazonSqsReceivedStamp|null $sqsReceivedStamp */
        $sqsReceivedStamp = $envelope->last(AmazonSqsReceivedStamp::class);

        if (null === $sqsReceivedStamp) {
            throw new LogicException('No AmazonSqsReceivedStamp found on the Envelope.');
        }

        return $sqsReceivedStamp;
    }
}
