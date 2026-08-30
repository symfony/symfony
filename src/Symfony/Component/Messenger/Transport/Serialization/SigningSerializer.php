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
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SigningSerializer implements SerializerInterface
{
    private const STAMP_HEADER_PREFIX = 'X-Message-Stamp-';

    // Tells apart a signature that covers the headers from an older one that covers the body alone.
    // HMACs are written in lowercase hexadecimal, so no older signature can ever start with this marker.
    // The marker also derives the key of the newer scheme, so that a signature stripped of it cannot
    // be replayed as a body-only signature over its own payload.
    private const SIGNATURE_MARKER = 'v2:';

    /**
     * @param list<class-string> $signedMessageTypes
     */
    public function __construct(
        private SerializerInterface $inner,
        #[\SensitiveParameter] private string|\Stringable $signingKey,
        private array $signedMessageTypes,
        private string $algorithm = 'sha256',
    ) {
    }

    public function encode(Envelope $envelope): array
    {
        $encoded = $this->inner->encode($envelope);
        $type = $envelope->getMessage()::class;

        if ($this->shouldSign($type)) {
            $encoded['headers']['Body-Sign'] = self::SIGNATURE_MARKER.hash_hmac($this->algorithm, self::getSignedPayload($encoded), $this->getSigningKey(false));
            $encoded['headers']['Sign-Algo'] = $this->algorithm;
        }

        return $encoded;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        // no message type requires signing: act as a pass-through for the inner serializer
        if (!$this->signedMessageTypes) {
            return $this->inner->decode($encodedEnvelope);
        }

        $headers = $encodedEnvelope['headers'] ?? [];
        $sign = $headers['Body-Sign'] ?? null;
        $sign = \is_string($sign) ? $sign : null;

        try {
            if ($sign) {
                // signatures written before the headers were covered carry no marker and authenticate the body alone
                $bodyOnly = !str_starts_with($sign, self::SIGNATURE_MARKER);
                $payload = $bodyOnly ? ($encodedEnvelope['body'] ?? '') : self::getSignedPayload($encodedEnvelope);
                $expected = ($bodyOnly ? '' : self::SIGNATURE_MARKER).hash_hmac($this->algorithm, $payload, $this->getSigningKey($bodyOnly));

                if (hash_equals($expected, $sign)) {
                    // A valid signature authenticates the message whatever its type: decode it without peeking.
                    // The algorithm is implied by the HMAC itself, so the "Sign-Algo" header isn't consulted here.
                    unset($encodedEnvelope['headers']['Body-Sign'], $encodedEnvelope['headers']['Sign-Algo']);

                    return $this->inner->decode($encodedEnvelope);
                }
            }

            $envelope = null;

            if (!$this->inner instanceof MessageTypeAwareSerializerInterface) {
                $envelope = $this->inner->decode($encodedEnvelope);
                $type = $envelope->getMessage()::class;
            } elseif (null === $type = $this->inner->getMessageType($encodedEnvelope)) {
                throw new InvalidMessageSignatureException('The message could not be verified and its type could not be determined; refusing to decode it.');
            }

            if (!$this->shouldSign($type)) {
                return $envelope ?? $this->inner->decode($encodedEnvelope);
            }

            if (!$sign) {
                throw new InvalidMessageSignatureException(\sprintf('Message "%s" requires a signature but none was found.', $type));
            }

            if ($this->algorithm !== $algo = $headers['Sign-Algo'] ?? $this->algorithm) {
                throw new InvalidMessageSignatureException(\sprintf('Expected "%s" signature algorithm for message "%s", "%s" given.', $this->algorithm, $type, $algo));
            }

            throw new InvalidMessageSignatureException(\sprintf('Invalid signature for message "%s".', $type));
        } catch (\Throwable $e) {
            return MessageDecodingFailedException::wrap($encodedEnvelope, $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private function shouldSign(string $type): bool
    {
        foreach ($this->signedMessageTypes as $signedType) {
            if (is_a($type, $signedType, true)) {
                return true;
            }
        }

        return false;
    }

    private function getSigningKey(bool $bodyOnly): string
    {
        return $bodyOnly ? $this->signingKey : hash_hmac($this->algorithm, self::SIGNATURE_MARKER, $this->signingKey);
    }

    /**
     * Returns the payload the signature covers: the body, plus the headers that
     * decide how the body is turned into an envelope. Transports add headers of
     * their own, so signing every header would break as soon as one of them does.
     */
    private static function getSignedPayload(array $encodedEnvelope): string
    {
        $headers = [];

        foreach ($encodedEnvelope['headers'] ?? [] as $name => $value) {
            if ('type' === $name || str_starts_with($name, self::STAMP_HEADER_PREFIX)) {
                $headers[$name] = $value;
            }
        }

        // transports are free to reorder headers, so the order must not matter
        ksort($headers, \SORT_STRING);

        // serialize() gives an unambiguous encoding of the pair; it is never read back
        return serialize([$encodedEnvelope['body'] ?? '', $headers]);
    }
}
