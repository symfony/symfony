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
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
class BeanstalkdTransport implements TransportInterface, KeepaliveReceiverInterface, MessageCountAwareInterface
{
    private SerializerInterface $serializer;
    private BeanstalkdReceiver $receiver;
    private BeanstalkdSender $sender;

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
        $fetchSize = \func_num_args() > 0 ? func_get_arg(0) : 1;

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

    public function getMessageCount(): int
    {
        return $this->getReceiver()->getMessageCount();
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    private function getReceiver(): BeanstalkdReceiver
    {
        return $this->receiver ??= new BeanstalkdReceiver($this->connection, $this->serializer);
    }

    private function getSender(): BeanstalkdSender
    {
        return $this->sender ??= new BeanstalkdSender($this->connection, $this->serializer);
    }
}
