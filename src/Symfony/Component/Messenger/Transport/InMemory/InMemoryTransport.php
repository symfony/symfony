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
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Transport that stays in memory. Useful for testing purpose.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryTransport implements TransportInterface, ListableReceiverInterface, MessageCountAwareInterface, ResetInterface
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

    /**
     * @param int $fetchSize Best-effort hint about how many messages can be received in one call
     */
    public function get(/* int $fetchSize = 1 */): iterable
    {
        $fetchSize = \func_num_args() > 0 ? max(1, func_get_arg(0)) : 1;
        $envelopes = [];
        $now = $this->clock?->now() ?? new \DateTimeImmutable();
        foreach ($this->decode($this->queue) as $id => $envelope) {
            if (!isset($this->availableAt[$id]) || $now > $this->availableAt[$id]) {
                $envelopes[] = $envelope;
                if (\count($envelopes) >= $fetchSize) {
                    break;
                }
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

    public function getMessageCount(): int
    {
        return \count($this->queue);
    }

    /**
     * Returns the queued envelopes in order, delayed ones included.
     *
     * @return Envelope[]
     */
    public function all(?int $limit = null): array
    {
        return array_values($this->decode(null === $limit ? $this->queue : \array_slice($this->queue, 0, $limit, true)));
    }

    public function find(mixed $id): ?Envelope
    {
        if (!\is_int($id) && !\is_string($id) || !isset($this->queue[$id])) {
            return null;
        }

        return $this->decode([$this->queue[$id]])[0];
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
            $this->availableAt[$id] = $now->modify(\sprintf('+%d milliseconds', $delayStamp->getDelay()));
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
