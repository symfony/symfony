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
    private $sent = [];

    /**
     * @var Envelope[]
     */
    private $acknowledged = [];

    /**
     * @var Envelope[]
     */
    private $rejected = [];

    /**
     * @var Envelope[]
     */
    private $queue = [];

    /**
     * @var SerializerInterface|null
     */
    private $serializer;

    public function __construct(SerializerInterface $serializer = null)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        return array_values($this->decode($this->queue));
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $this->acknowledged[] = $this->encode($envelope);
        $id = spl_object_hash($envelope->getMessage());
        unset($this->queue[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $this->encode($envelope);
        $id = spl_object_hash($envelope->getMessage());
        unset($this->queue[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $encodedEnvelope = $this->encode($envelope);
        $this->sent[] = $encodedEnvelope;
        $id = spl_object_hash($envelope->getMessage());
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

    /**
     * @return Envelope|array
     */
    private function encode(Envelope $envelope)
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

        return array_map(
            [$this->serializer, 'decode'],
            $messagesEncoded
        );
    }
}
