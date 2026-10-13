<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Exchanges messages with another application: sends no stamps, and ignores the stamps of the messages it receives.
 *
 * Stamp header mappers can still send some stamps. A mapper writes these stamps as plain headers, and reads them back from the headers it receives.
 * The messages sent back for retries or to a failure transport keep their stamps, so that their retries are counted.
 * Decorate Serializer: this class handles the "X-Message-Stamp-*" headers it writes, while PhpSerializer keeps the stamps in the body.
 * Combine with #[AsMessage(serializedTypeName: ...)] so that both applications agree on the type of the message.
 */
final class InteropSerializer implements SerializerInterface, MessageTypeAwareSerializerInterface
{
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    /**
     * @param string|(\Closure(array{body: string, headers?: array<string, string>}): ?string)|null $messageType        The type of the messages that have no "type" header, or a closure that tells it from their encoded envelope without decoding the body into objects
     * @param iterable<StampHeaderMapperInterface>                                                  $stampHeaderMappers The mappers between stamps and plain headers, the first one that maps a header or a stamp class wins
     */
    public function __construct(
        private SerializerInterface $inner,
        private string|\Closure|null $messageType = null,
        private iterable $stampHeaderMappers = [new IdentityStampHeaderMapper()],
    ) {
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $headers = \is_array($encodedEnvelope['headers'] ?? null) ? $encodedEnvelope['headers'] : [];
        $encodedEnvelope = $this->withMessageType($encodedEnvelope, $isTypeUnknown);

        // encode() keeps the stamps only for retries and failure transports, which always add a RedeliveryStamp
        $isRetry = isset($headers[self::STAMP_HEADER_PREFIX.RedeliveryStamp::class]);

        foreach ($headers as $name => $value) {
            if (str_starts_with($name, self::STAMP_HEADER_PREFIX) && (!$isRetry || !class_exists(substr($name, \strlen(self::STAMP_HEADER_PREFIX))))) {
                unset($encodedEnvelope['headers'][$name]);
            }
        }

        $envelope = $this->inner->decode($encodedEnvelope);

        if ($isTypeUnknown && ($failure = $envelope->getMessage()) instanceof MessageDecodingFailedException) {
            // the failure keeps the stamps that decoded, so that its retries are counted
            $envelope = MessageDecodingFailedException::wrap($failure->encodedEnvelope, 'Encoded envelope does not have a "type" header and the message type closure returned null.')->with(...array_merge(...array_values($envelope->all())));
        }

        foreach ($this->stampHeaderMappers as $mapper) {
            $stamps = [];

            // the envelope keeps the stamp classes it already has, from the "X-Message-Stamp-*" headers of a retry or from a previous mapper,
            // and the stamps that are never sent cannot come from a transport
            foreach ($mapper->toStamps($headers) as $stamp) {
                if (!$stamp instanceof NonSendableStampInterface && null === $envelope->last($stamp::class)) {
                    $stamps[] = $stamp;
                }
            }

            $envelope = $envelope->with(...$stamps);
        }

        return $envelope;
    }

    public function encode(Envelope $envelope): array
    {
        $encoded = $this->inner->encode($envelope);
        $sendable = $envelope->withoutStampsOfType(NonSendableStampInterface::class);

        foreach ($this->stampHeaderMappers as $mapper) {
            // the headers of the inner serializer decide how the message is decoded, so a mapper cannot replace them
            $encoded['headers'] = ($encoded['headers'] ?? []) + $mapper->toHeaders($sendable);
        }

        // retries and failure transports send a received message back with a RedeliveryStamp and need its stamps to count retries,
        // while a received message relayed elsewhere, by an outbox or a scheduler, has none
        if ($envelope->last(ReceivedStamp::class) && $envelope->last(RedeliveryStamp::class)) {
            return $encoded;
        }

        foreach ($encoded['headers'] ?? [] as $name => $value) {
            if (str_starts_with($name, self::STAMP_HEADER_PREFIX)) {
                unset($encoded['headers'][$name]);
            }
        }

        return $encoded;
    }

    public function getMessageType(array $encodedEnvelope): ?string
    {
        return $this->inner instanceof MessageTypeAwareSerializerInterface ? $this->inner->getMessageType($this->withMessageType($encodedEnvelope)) : null;
    }

    private function withMessageType(array $encodedEnvelope, ?bool &$isTypeUnknown = null): array
    {
        if (null === $this->messageType || !\is_array($encodedEnvelope['headers'] ?? []) || !empty($encodedEnvelope['headers']['type'])) {
            return $encodedEnvelope;
        }

        if (null !== $type = \is_string($this->messageType) ? $this->messageType : ($this->messageType)(array_diff_key($encodedEnvelope, ['extra' => true]))) {
            $encodedEnvelope['headers']['type'] = $type;
        }
        $isTypeUnknown = null === $type;

        return $encodedEnvelope;
    }
}
