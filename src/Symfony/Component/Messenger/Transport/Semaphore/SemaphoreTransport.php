<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Semaphore;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Cedrick Oka Baidai <okacedrick@gmail.com>
 */
class SemaphoreTransport implements TransportInterface
{
    private $connection;
    private $serializer;

    /**
     * @var ReceiverInterface
     */
    private $receiver;

    /**
     * @var SenderInterface
     */
    private $sender;

    public function __construct(Connection $connection, SerializerInterface $serializer = null)
    {
        $this->connection = $connection;
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::get()
     */
    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::ack()
     */
    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface::reject()
     */
    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Messenger\Transport\Sender\SenderInterface::send()
     */
    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    private function getReceiver(): ReceiverInterface
    {
        if (null === $this->receiver) {
            $this->receiver = new SemaphoreReceiver($this->connection, $this->serializer);
        }

        return $this->receiver;
    }

    private function getSender(): SenderInterface
    {
        if (null === $this->sender) {
            $this->sender = new SemaphoreSender($this->connection, $this->serializer);
        }

        return $this->sender;
    }
}
