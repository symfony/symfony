<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Transport that stays in memory. Useful for testing purpose.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryTransport implements TransportInterface, ResetInterface
{
    /**
     * @var Envelope[]
     */
    private array $sent = [];

    /**
     * @var Envelope[]
     */
    private array $acknowledged = [];

    /**
     * @var Envelope[]
     */
    private array $rejected = [];

    /**
     * @var Envelope[]
     */
    private array $queue = [];

    private int $nextId = 1;
    private ?SerializerInterface $serializer;

    public function __construct(SerializerInterface $serializer = null)
    {
        $this->serializer = $serializer;
    }

    public function get(): iterable
    {
        return array_values($this->decode($this->queue));
    }

    public function ack(Envelope $envelope): void
    {
        $this->acknowledged[] = $this->encode($envelope);

        if (!$transportMessageIdStamp = $envelope->last(TransportMessageIdStamp::class)) {
            throw new LogicException('No TransportMessageIdStamp found on the Envelope.');
        }

        unset($this->queue[$transportMessageIdStamp->getId()]);
    }

    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $this->encode($envelope);

        if (!$transportMessageIdStamp = $envelope->last(TransportMessageIdStamp::class)) {
            throw new LogicException('No TransportMessageIdStamp found on the Envelope.');
        }

        unset($this->queue[$transportMessageIdStamp->getId()]);
    }

    public function send(Envelope $envelope): Envelope
    {
        $id = $this->nextId++;
        $envelope = $envelope->with(new TransportMessageIdStamp($id));
        $encodedEnvelope = $this->encode($envelope);
        $this->sent[] = $encodedEnvelope;
        $this->queue[$id] = $encodedEnvelope;

        return $envelope;
    }

    public function reset()
    {
        $this->sent = $this->queue = $this->rejected = $this->acknowledged = [];
    }

    /**
     * @return Envelope[]
     */
    public function getAcknowledged(): array
    {
        return $this->decode($this->acknowledged);
    }

    /**
     * @return Envelope[]
     */
    public function getRejected(): array
    {
        return $this->decode($this->rejected);
    }

    /**
     * @return Envelope[]
     */
    public function getSent(): array
    {
        return $this->decode($this->sent);
    }

    private function encode(Envelope $envelope): Envelope|array
    {
        if (null === $this->serializer) {
            return $envelope;
        }

        return $this->serializer->encode($envelope);
    }

    /**
     * @param array<mixed> $messagesEncoded
     *
     * @return Envelope[]
     */
    private function decode(array $messagesEncoded): array
    {
        if (null === $this->serializer) {
            return $messagesEncoded;
        }

        return array_map($this->serializer->decode(...), $messagesEncoded);
    }
}
