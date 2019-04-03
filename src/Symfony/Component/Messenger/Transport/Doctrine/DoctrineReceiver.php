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

use Doctrine\DBAL\DBALException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 *
 * @experimental in 4.3
 */
class DoctrineReceiver implements ReceiverInterface, MessageCountAwareInterface
{
    private $connection;
    private $serializer;

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
        try {
            $doctrineEnvelope = $this->connection->get();
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (null === $doctrineEnvelope) {
            return [];
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $doctrineEnvelope['body'],
                'headers' => $doctrineEnvelope['headers'],
            ]);
        } catch (MessageDecodingFailedException $exception) {
            $this->connection->reject($doctrineEnvelope['id']);

            throw $exception;
        }

        yield $envelope->with(new DoctrineReceivedStamp($doctrineEnvelope['id']));
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $this->connection->ack($this->findDoctrineReceivedStamp($envelope)->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->connection->reject($this->findDoctrineReceivedStamp($envelope)->getId());
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageCount(): int
    {
        return $this->connection->getMessageCount();
    }

    private function findDoctrineReceivedStamp(Envelope $envelope): DoctrineReceivedStamp
    {
        /** @var DoctrineReceivedStamp|null $doctrineReceivedStamp */
        $doctrineReceivedStamp = $envelope->last(DoctrineReceivedStamp::class);

        if (null === $doctrineReceivedStamp) {
            throw new LogicException('No DoctrineReceivedStamp found on the Envelope.');
        }

        return $doctrineReceivedStamp;
    }
}
