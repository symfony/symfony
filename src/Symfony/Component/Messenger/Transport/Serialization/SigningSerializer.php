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
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Stamp\UnverifiedDecodingFailureStamp;

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

    // Marks a message that is signed although nothing verified it: the signature covers this header, so that adding or removing it breaks the signature.
    private const UNVERIFIED_HEADER = 'Sign-Trust';

    private bool $signAll;

    /**
     * @param list<class-string|'*'> $signedMessageTypes The message types that require a verified signature, or "*" to sign every message and to refuse a message without a valid signature before reading its type
     * @param bool                   $acceptUnverified   Whether a serializer that signs every message accepts the messages signed as unverified, as the one of a failure transport does
     */
    public function __construct(
        private SerializerInterface $inner,
        #[\SensitiveParameter] private string|\Stringable $signingKey,
        private array $signedMessageTypes,
        private string $algorithm = 'sha256',
        private bool $acceptUnverified = false,
    ) {
        $this->signAll = \in_array('*', $signedMessageTypes, true);
    }

    public function encode(Envelope $envelope): array
    {
        $encoded = $this->inner->encode($envelope);

        if (!$this->signedMessageTypes) {
            return $encoded;
        }

        $trusted = TrustStamp::isEnvelopeTrusted($envelope);

        if (($message = $envelope->getMessage()) instanceof MessageDecodingFailedException) {
            // the inner serializer can send the failed envelope again as it is: it must have been signed with this key, and not as unverified
            unset($encoded['headers']['Body-Sign'], $encoded['headers']['Sign-Algo'], $encoded['headers'][self::UNVERIFIED_HEADER]);
            $trusted = $trusted && $this->hasValidSignature($message->encodedEnvelope, false) && !isset($message->encodedEnvelope['headers'][self::UNVERIFIED_HEADER]);

            // a message refused for its signature stays refused: signing its failure, even as unverified, would let a failure transport accept it
            $sign = !$message->getPrevious() instanceof InvalidMessageSignatureException && ($this->signAll || $this->hasValidSignature($message->encodedEnvelope, true));
        } else {
            $sign = $this->signAll || $this->shouldSign($message::class);
        }

        if ($sign) {
            if ($trusted) {
                unset($encoded['headers'][self::UNVERIFIED_HEADER]);
            } else {
                $encoded['headers'][self::UNVERIFIED_HEADER] = 'unverified';
            }

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
            if ($signed = $this->hasValidSignature($encodedEnvelope, !$this->signAll)) {
                // The algorithm is implied by the HMAC itself, so the "Sign-Algo" header isn't consulted here.
                $signedEnvelope = $encodedEnvelope;
                unset($signedEnvelope['headers']['Body-Sign'], $signedEnvelope['headers']['Sign-Algo'], $signedEnvelope['headers'][self::UNVERIFIED_HEADER]);
            }

            if ($signed && !isset($headers[self::UNVERIFIED_HEADER])) {
                // A valid signature authenticates the message whatever its type: decode it without peeking.
                $envelope = $this->inner->decode($signedEnvelope);
                $failure = $envelope->getMessage();

                // a body-only signature leaves the headers unverified
                $trust = str_starts_with($sign, self::SIGNATURE_MARKER) ? [TrustStamp::trusted()] : [];

                if (!$failure instanceof MessageDecodingFailedException || $this->hasValidSignature($failure->encodedEnvelope, !$this->signAll)) {
                    return $envelope->with(...$trust);
                }

                // the failed envelope is decoded again later, so it must keep its signature
                return MessageDecodingFailedException::wrap($encodedEnvelope, $failure->getMessage(), $failure->getCode(), $failure->getPrevious())->with(...array_merge(...array_values($envelope->all())), ...$trust);
            }

            if ($this->signAll && (!$signed || !$this->acceptUnverified)) {
                // nothing that the signature does not cover is read: neither the type of the message nor its stamps
                $error = match (true) {
                    $signed => 'The message is signed as unverified: only a failure transport accepts it.',
                    null === $sign => 'The message requires a signature but none was found.',
                    !str_starts_with($sign, self::SIGNATURE_MARKER) => 'The signature of the message does not cover its headers.',
                    $this->algorithm !== $algo = $headers['Sign-Algo'] ?? $this->algorithm => \sprintf('Expected "%s" signature algorithm, "%s" given.', $this->algorithm, $algo),
                    default => 'Invalid message signature.',
                };

                throw new InvalidMessageSignatureException($error);
            }

            // a message signed as unverified is read like an unsigned one: the signature only tells that this key signed it as such
            $unverifiedEnvelope = $signed ? $signedEnvelope : $encodedEnvelope;
            $envelope = null;

            if (!$this->inner instanceof MessageTypeAwareSerializerInterface) {
                $envelope = $this->inner->decode($unverifiedEnvelope);
                $type = $envelope->getMessage()::class;
            } elseif (null === $type = $this->inner->getMessageType($unverifiedEnvelope)) {
                throw new InvalidMessageSignatureException('The message could not be verified and its type could not be determined; refusing to decode it.');
            }

            if (!$this->shouldSign($type)) {
                $envelope ??= $this->inner->decode($unverifiedEnvelope);
                $failure = $envelope->getMessage();
                $trust = $signed ? [TrustStamp::untrusted()] : [];

                if (!$failure instanceof MessageDecodingFailedException) {
                    return $envelope->with(...$trust);
                }

                // encode() signs a failure that carries a signed envelope. An unsigned one is forged: it would attach unverified stamps to that envelope.
                if (!$signed && $this->hasValidSignature($failure->encodedEnvelope, true)) {
                    throw new InvalidMessageSignatureException('The message is an unsigned decoding failure that carries a signed envelope; refusing to decode it.');
                }

                // a claim reference it carries is verified only once retrieved: the stamps of this failure must not reach the signed message it resolves to
                return $envelope->with(new UnverifiedDecodingFailureStamp(array_merge(...array_values($envelope->all())), $this->signedMessageTypes), ...$trust);
            }

            if ($signed) {
                throw new InvalidMessageSignatureException(\sprintf('Message "%s" requires a verified signature, but it is signed as unverified.', $type));
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

    private function hasValidSignature(array $encodedEnvelope, bool $acceptBodyOnly): bool
    {
        $sign = $encodedEnvelope['headers']['Body-Sign'] ?? null;
        $body = $encodedEnvelope['body'] ?? '';

        if (!\is_string($sign) || !\is_string($body)) {
            return false;
        }

        // signatures written before the headers were covered carry no marker and authenticate the body alone
        if (($bodyOnly = !str_starts_with($sign, self::SIGNATURE_MARKER)) && !$acceptBodyOnly) {
            return false;
        }

        $payload = $bodyOnly ? $body : self::getSignedPayload($encodedEnvelope);

        return hash_equals(($bodyOnly ? '' : self::SIGNATURE_MARKER).hash_hmac($this->algorithm, $payload, $this->getSigningKey($bodyOnly)), $sign);
    }

    private function getSigningKey(bool $bodyOnly): string
    {
        return $bodyOnly ? $this->signingKey : hash_hmac($this->algorithm, self::SIGNATURE_MARKER, $this->signingKey);
    }

    /**
     * Returns the payload the signature covers: the body, plus the headers that
     * decide how the body is turned into an envelope and whether it is verified. Transports add headers of
     * their own, so signing every header would break as soon as one of them does.
     */
    private static function getSignedPayload(array $encodedEnvelope): string
    {
        $headers = [];

        foreach ($encodedEnvelope['headers'] ?? [] as $name => $value) {
            if ('type' === $name || self::UNVERIFIED_HEADER === $name || str_starts_with($name, self::STAMP_HEADER_PREFIX)) {
                $headers[$name] = $value;
            }
        }

        // transports are free to reorder headers, so the order must not matter
        ksort($headers, \SORT_STRING);

        // serialize() gives an unambiguous encoding of the pair; it is never read back
        return serialize([$encodedEnvelope['body'] ?? '', $headers]);
    }
}
