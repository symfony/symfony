<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Beanstalkd\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\SentForRetryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
class BeanstalkdReceiver implements KeepaliveReceiverInterface, MessageCountAwareInterface
{
    private SerializerInterface $serializer;

    public function __construct(
        private Connection $connection,
        ?SerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * @param int $fetchSize
     */
    public function get(/* int $fetchSize = 1 */): iterable
    {
        if (!$beanstalkdEnvelope = $this->connection->get()) {
            return;
        }

        $stamps = [
            new BeanstalkdReceivedStamp($beanstalkdEnvelope['id'], $this->connection->getTube()),
            new TransportMessageIdStamp($beanstalkdEnvelope['id']),
            new BeanstalkdPriorityStamp($this->connection->getMessagePriority($beanstalkdEnvelope['id'])),
        ];

        try {
            yield $this->serializer->decode($beanstalkdEnvelope = [
                'body' => $beanstalkdEnvelope['body'],
                'headers' => $beanstalkdEnvelope['headers'],
            ])->withoutAll(TransportMessageIdStamp::class)->with(...$stamps);
        } catch (MessageDecodingFailedException $e) {
            yield MessageDecodingFailedException::wrap($beanstalkdEnvelope, $e->getMessage(), $e->getCode(), $e)->with(...$stamps);
        }
    }

    public function ack(Envelope $envelope): void
    {
        $this->connection->ack($this->findBeanstalkdReceivedStampId($envelope));
    }

    public function reject(Envelope $envelope): void
    {
        $this->connection->reject(
            $this->findBeanstalkdReceivedStampId($envelope),
            $envelope->last(BeanstalkdPriorityStamp::class)?->priority,
            $envelope->last(SentForRetryStamp::class)?->isSent ?? false,
        );
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        $this->connection->keepalive($this->findBeanstalkdReceivedStampId($envelope), $seconds);
    }

    public function getMessageCount(): int
    {
        return $this->connection->getMessageCount();
    }

    private function findBeanstalkdReceivedStampId(Envelope $envelope): string
    {
        return $envelope->last(BeanstalkdReceivedStamp::class)?->getId() ?? throw new LogicException('No BeanstalkdReceivedStamp found on the Envelope.');
    }
}
