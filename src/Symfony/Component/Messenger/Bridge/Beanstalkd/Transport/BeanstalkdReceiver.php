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

    public function get(): iterable
    {
        $beanstalkdEnvelope = $this->connection->get();

        if (null === $beanstalkdEnvelope) {
            return [];
        }

        try {
            $envelope = $this->serializer->decode([
                'body' => $beanstalkdEnvelope['body'],
                'headers' => $beanstalkdEnvelope['headers'],
            ]);
        } catch (MessageDecodingFailedException $exception) {
            $this->connection->reject($beanstalkdEnvelope['id']);

            throw $exception;
        }

        return [$envelope->with(new BeanstalkdReceivedStamp($beanstalkdEnvelope['id'], $this->connection->getTube()))];
    }

    public function ack(Envelope $envelope): void
    {
        $this->connection->ack($this->findBeanstalkdReceivedStamp($envelope)->getId());
    }

    public function reject(Envelope $envelope): void
    {
        $this->connection->reject($this->findBeanstalkdReceivedStamp($envelope)->getId());
    }

    public function keepalive(Envelope $envelope): void
    {
        $this->connection->keepalive($this->findBeanstalkdReceivedStamp($envelope)->getId());
    }

    public function getMessageCount(): int
    {
        return $this->connection->getMessageCount();
    }

    private function findBeanstalkdReceivedStamp(Envelope $envelope): BeanstalkdReceivedStamp
    {
        /** @var BeanstalkdReceivedStamp|null $beanstalkdReceivedStamp */
        $beanstalkdReceivedStamp = $envelope->last(BeanstalkdReceivedStamp::class);

        if (null === $beanstalkdReceivedStamp) {
            throw new LogicException('No BeanstalkdReceivedStamp found on the Envelope.');
        }

        return $beanstalkdReceivedStamp;
    }
}
