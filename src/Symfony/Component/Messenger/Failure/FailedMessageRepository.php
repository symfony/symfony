<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Failure;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Reads and removes the messages pending in the failure transports.
 *
 * This is what the "messenger:failed:*" commands are built on, so that listing,
 * inspecting, removing and redispatching a failed message can also be done from
 * outside the console.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class FailedMessageRepository
{
    public function __construct(
        /**
         * @var ServiceProviderInterface<ReceiverInterface>
         */
        private ServiceProviderInterface $failureTransports,
        private ?string $globalFailureTransportName = null,
        private ?PhpSerializer $phpSerializer = null,
        private ?MessageBusInterface $messageBus = null,
    ) {
    }

    public static function getMessageId(Envelope $envelope): mixed
    {
        return $envelope->last(TransportMessageIdStamp::class)?->getId();
    }

    /**
     * Strips the stamps that must not survive a redispatch.
     */
    public static function prepareForRedispatch(Envelope $envelope): Envelope
    {
        return $envelope
            ->withoutStampsOfType(NonSendableStampInterface::class)
            ->withoutAll(SentToFailureTransportStamp::class)
            ->withoutAll(TransportMessageIdStamp::class);
    }

    /**
     * @return list<string>
     */
    public function getTransportNames(): array
    {
        return array_keys($this->failureTransports->getProvidedServices());
    }

    public function getGlobalTransportName(): ?string
    {
        return $this->globalFailureTransportName;
    }

    public function supportsListing(?string $transport = null): bool
    {
        return $this->getReceiver($transport) instanceof ListableReceiverInterface;
    }

    /**
     * @return int|null The number of pending messages, or null when the transport cannot count them
     */
    public function count(?string $transport = null): ?int
    {
        $receiver = $this->getReceiver($transport);

        return $receiver instanceof MessageCountAwareInterface ? $receiver->getMessageCount() : null;
    }

    public function find(mixed $id, ?string $transport = null): ?Envelope
    {
        $receiver = $this->getListableReceiver($transport);

        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            return $receiver->find($id);
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }
    }

    /**
     * The limit bounds what is read from the transport, before the filter is applied.
     *
     * @return iterable<Envelope>
     */
    public function all(?string $transport = null, ?FailedMessageFilter $filter = null, ?int $limit = null): iterable
    {
        // resolve the receiver now so that an unusable transport is reported by
        // this call rather than by the first iteration of the returned generator
        $receiver = $this->getListableReceiver($transport);

        return $this->iterate($receiver, $filter, $limit);
    }

    public function remove(Envelope $envelope, ?string $transport = null): void
    {
        $this->getReceiver($transport)->reject($envelope);
    }

    /**
     * Dispatches the message again and drops it from the failure transport.
     */
    public function redispatch(Envelope $envelope, ?string $transport = null): void
    {
        if (!$this->messageBus) {
            throw new LogicException('Cannot redispatch a failed message without a message bus.');
        }

        $this->messageBus->dispatch(self::prepareForRedispatch($envelope));

        // ack rather than reject: the failure transport is done with this message
        $this->getReceiver($transport)->ack($envelope);
    }

    public function getReceiver(?string $transport = null): ReceiverInterface
    {
        if (null === $transport ??= $this->globalFailureTransportName) {
            throw new InvalidArgumentException(\sprintf('No default failure transport is defined. Available transports are: "%s".', implode('", "', $this->getTransportNames())));
        }

        if (!$this->failureTransports->has($transport)) {
            throw new InvalidArgumentException(\sprintf('The "%s" failure transport was not found. Available transports are: "%s".', $transport, implode('", "', $this->getTransportNames())));
        }

        return $this->failureTransports->get($transport);
    }

    private function getListableReceiver(?string $transport): ListableReceiverInterface
    {
        if (!($receiver = $this->getReceiver($transport)) instanceof ListableReceiverInterface) {
            throw new InvalidArgumentException(\sprintf('The "%s" failure transport does not support listing messages.', $transport ?? $this->globalFailureTransportName));
        }

        return $receiver;
    }

    /**
     * @return \Generator<Envelope>
     */
    private function iterate(ListableReceiverInterface $receiver, ?FailedMessageFilter $filter, ?int $limit): \Generator
    {
        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            foreach ($receiver->all($limit) as $envelope) {
                if (!$filter || $filter->matches($envelope)) {
                    yield $envelope;
                }
            }
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }
    }
}
