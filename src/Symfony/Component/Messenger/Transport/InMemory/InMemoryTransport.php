<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\InMemory;

use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
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
    private array $availableAt = [];

    public function __construct(
        private ?SerializerInterface $serializer = null,
        private ?ClockInterface $clock = null,
    ) {
    }

    public function get(): iterable
    {
        $envelopes = [];
        $now = $this->clock?->now() ?? new \DateTimeImmutable();
        foreach ($this->decode($this->queue) as $id => $envelope) {
            if (!isset($this->availableAt[$id]) || $now > $this->availableAt[$id]) {
                $envelopes[] = $envelope;
            }
        }

        return $envelopes;
    }

    public function ack(Envelope $envelope): void
    {
        $this->acknowledged[] = $this->encode($envelope);

        if (!$transportMessageIdStamp = $envelope->last(TransportMessageIdStamp::class)) {
            throw new LogicException('No TransportMessageIdStamp found on the Envelope.');
        }

        unset($this->queue[$id = $transportMessageIdStamp->getId()], $this->availableAt[$id]);
    }

    public function reject(Envelope $envelope): void
    {
        $this->rejected[] = $this->encode($envelope);

        if (!$transportMessageIdStamp = $envelope->last(TransportMessageIdStamp::class)) {
            throw new LogicException('No TransportMessageIdStamp found on the Envelope.');
        }

        unset($this->queue[$id = $transportMessageIdStamp->getId()], $this->availableAt[$id]);
    }

    public function send(Envelope $envelope): Envelope
    {
        $id = $this->nextId++;
        $envelope = $envelope->with(new TransportMessageIdStamp($id));
        $encodedEnvelope = $this->encode($envelope);
        $this->sent[] = $encodedEnvelope;
        $this->queue[$id] = $encodedEnvelope;

        /** @var DelayStamp|null $delayStamp */
        if ($delayStamp = $envelope->last(DelayStamp::class)) {
            $now = $this->clock?->now() ?? new \DateTimeImmutable();
            $this->availableAt[$id] = $now->modify(\sprintf('+%d seconds', $delayStamp->getDelay() / 1000));
        }

        return $envelope;
    }

    public function reset(): void
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
