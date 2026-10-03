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
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Exchanges messages with another application: sends no stamps, and ignores the stamps it does not know.
 *
 * Combine with #[AsMessage(serializedTypeName: ...)] so that both applications agree on the type of the message.
 */
final class InteropSerializer implements SerializerInterface, MessageTypeAwareSerializerInterface
{
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    public function __construct(
        private SerializerInterface $inner,
    ) {
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        foreach (\is_array($encodedEnvelope['headers'] ?? null) ? $encodedEnvelope['headers'] : [] as $name => $value) {
            if (str_starts_with($name, self::STAMP_HEADER_PREFIX) && !class_exists(substr($name, \strlen(self::STAMP_HEADER_PREFIX)))) {
                unset($encodedEnvelope['headers'][$name]);
            }
        }

        return $this->inner->decode($encodedEnvelope);
    }

    public function encode(Envelope $envelope): array
    {
        $encoded = $this->inner->encode($envelope);

        // a received message is sent back to its own transport for retries, which need its stamps to count them
        if ($envelope->last(ReceivedStamp::class)) {
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
        return $this->inner instanceof MessageTypeAwareSerializerInterface ? $this->inner->getMessageType($encodedEnvelope) : null;
    }
}
