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

use Pheanstalk\Contract\PheanstalkInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\PriorityStamp;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
class BeanstalkdSender implements SenderInterface
{
    private Connection $connection;
    private SerializerInterface $serializer;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);

        /** @var DelayStamp|null $delayStamp */
        $delayStamp = $envelope->last(DelayStamp::class);
        $delayInMs = null !== $delayStamp ? $delayStamp->getDelay() : 0;

        /** @var PriorityStamp|null $priorityStamp */
        $priorityStamp = $envelope->last(PriorityStamp::class);
        $priority = $this->getPheanstalkPriority($priorityStamp);

        $this->connection->send($encodedMessage['body'], $encodedMessage['headers'] ?? [], $delayInMs, $priority);

        return $envelope;
    }

    /**
     * Beanstalkd supports u32 priorities (0 to 2^32 - 1), with 0 being the highest.
     * RabbitMQ supports u8 priorities (0 to 255), with 255 being the highest.
     * To provide interoperability, use RabbitMQ model.
     */
    private function getPheanstalkPriority(?PriorityStamp $stamp): int
    {
        if (null !== $stamp) {
            return PriorityStamp::MAX_PRIORITY - $stamp->getPriority();
        }

        return PheanstalkInterface::DEFAULT_PRIORITY;
    }
}
