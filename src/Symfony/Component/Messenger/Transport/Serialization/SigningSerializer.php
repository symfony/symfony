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
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
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

    // Lists the headers the signature covers, in addition to the headers that describe the message.
    // The headers that describe the message are "type", "Sign-Trust" and the stamps, and they are signed whenever present.
    // Transports add headers of their own, so the other headers that are not listed are dropped instead of breaking the signature.
    // Without other headers to sign, the list is left out, so that the message is signed as before the list existed.
    private const SIGNED_HEADERS = 'Sign-Headers';

    // Never signed: AmqpSender moves it to a property of the AMQP message, and the serializers read the format from their configuration instead.
    private const CONTENT_TYPE_HEADER = 'Content-Type';

    private array $signingKeys;
    private string $algorithm;
    private bool $acceptUnverified;

    /**
     * The message types that require a signature, when not every message is signed.
     *
     * @var list<class-string>|null
     */
    private ?array $signedMessageTypes = null;

    /**
     * Signs every message, and refuses a message without a valid signature before reading it.
     *
     * @param string|\Stringable|non-empty-list<string|\Stringable> $signingKey       The key, or a list of keys to rotate it: the first one signs, all of them verify
     * @param string|list<class-string>                             $algorithm        The hashing algorithm of the HMAC, passing a list of message types instead is deprecated since Symfony 8.2
     * @param bool|string                                           $acceptUnverified Whether to accept the messages signed as unverified, as the serializer of a failure transport does
     */
    public function __construct(
        private SerializerInterface $inner,
        #[\SensitiveParameter] string|\Stringable|array $signingKey,
        array|string $algorithm = 'sha256',
        string|bool $acceptUnverified = false,
    ) {
        if (!$this->signingKeys = \is_array($signingKey) ? array_values($signingKey) : [$signingKey]) {
            throw new InvalidArgumentException('At least one signing key is required.');
        }

        if (\is_array($algorithm)) {
            trigger_deprecation('symfony/messenger', '8.2', 'Passing a list of message types to "%s()" is deprecated, pass the algorithm instead to sign every message.', __METHOD__);

            $this->signedMessageTypes = $algorithm;
            $algorithm = \is_string($acceptUnverified) ? $acceptUnverified : 'sha256';
            $acceptUnverified = false;
        } elseif (!\is_bool($acceptUnverified)) {
            throw new \TypeError(\sprintf('Argument 4 passed to "%s()" must be of type bool, string given.', __METHOD__));
        }

        $this->algorithm = $algorithm;
        $this->acceptUnverified = $acceptUnverified;
    }

    /**
     * Signs only the messages of the given types, for the transports that do not sign every message.
     *
     * @param list<class-string> $signedMessageTypes
     *
     * @internal
     */
    public static function forMessageTypes(SerializerInterface $inner, #[\SensitiveParameter] string|\Stringable|array $signingKey, array $signedMessageTypes, string $algorithm = 'sha256'): self
    {
        $serializer = new self($inner, $signingKey, $algorithm);
        $serializer->signedMessageTypes = $signedMessageTypes;

        return $serializer;
    }

    public function encode(Envelope $envelope): array
    {
        $encoded = $this->inner->encode($envelope);

        if ([] === $this->signedMessageTypes) {
            return $encoded;
        }

        $signAll = null === $this->signedMessageTypes;
        $trusted = TrustStamp::isEnvelopeTrusted($envelope);

        if (($message = $envelope->getMessage()) instanceof MessageDecodingFailedException) {
            // the inner serializer can send the failed envelope again as it is: it must have been signed with one of the keys, and not as unverified
            unset($encoded['headers']['Body-Sign'], $encoded['headers']['Sign-Algo'], $encoded['headers'][self::UNVERIFIED_HEADER], $encoded['headers'][self::SIGNED_HEADERS]);
            $trusted = $trusted && $this->hasValidSignature($message->encodedEnvelope, false) && !isset($message->encodedEnvelope['headers'][self::UNVERIFIED_HEADER]);

            // a message refused for its signature stays refused: signing its failure, even as unverified, would let a failure transport accept it
            $sign = !$message->getPrevious() instanceof InvalidMessageSignatureException && ($signAll || $this->hasValidSignature($message->encodedEnvelope, true));
        } elseif ($sign = $signAll || $this->shouldSign($message::class)) {
            if (!$signAll) {
                self::triggerSignedMessageTypeDeprecation($message::class);
            }
        }

        if ($sign) {
            if ($trusted) {
                unset($encoded['headers'][self::UNVERIFIED_HEADER]);
            } else {
                $encoded['headers'][self::UNVERIFIED_HEADER] = 'unverified';
            }

            unset($encoded['headers'][self::SIGNED_HEADERS]);
            $names = [];

            foreach ($encoded['headers'] ?? [] as $name => $value) {
                if (self::CONTENT_TYPE_HEADER !== $name && !self::describesTheMessage($name)) {
                    $names[] = (string) $name;
                }
            }

            if ($names) {
                $encoded['headers'][self::SIGNED_HEADERS] = json_encode($names, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            }

            $encoded['headers']['Body-Sign'] = self::SIGNATURE_MARKER.hash_hmac($this->algorithm, self::getSignedPayload($encoded), $this->getSigningKey($this->signingKeys[0], false));
            $encoded['headers']['Sign-Algo'] = $this->algorithm;
        }

        return $encoded;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        // no message type requires signing: act as a pass-through for the inner serializer
        if ([] === $this->signedMessageTypes) {
            return $this->inner->decode($encodedEnvelope);
        }

        $signAll = null === $this->signedMessageTypes;
        $headers = $encodedEnvelope['headers'] ?? [];
        $sign = $headers['Body-Sign'] ?? null;
        $sign = \is_string($sign) ? $sign : null;

        try {
            if ($signed = $this->hasValidSignature($encodedEnvelope, !$signAll)) {
                if (str_starts_with($sign, self::SIGNATURE_MARKER)) {
                    // the inner serializer only gets the signed headers, and so does a decode failure, as a failure transport signs it again
                    $encodedEnvelope['headers'] = $headers = array_intersect_key($headers, self::getSignedHeaders($encodedEnvelope) + ['Body-Sign' => true, 'Sign-Algo' => true, self::CONTENT_TYPE_HEADER => true]);
                }

                // The algorithm is implied by the HMAC itself, so the "Sign-Algo" header isn't consulted here.
                $signedEnvelope = $encodedEnvelope;
                unset($signedEnvelope['headers']['Body-Sign'], $signedEnvelope['headers']['Sign-Algo'], $signedEnvelope['headers'][self::UNVERIFIED_HEADER], $signedEnvelope['headers'][self::SIGNED_HEADERS]);
            }

            if ($signed && !isset($headers[self::UNVERIFIED_HEADER])) {
                // A valid signature authenticates the message whatever its type: decode it without peeking.
                $envelope = $this->inner->decode($signedEnvelope);
                $failure = $envelope->getMessage();

                // a body-only signature leaves the headers unverified
                $trust = str_starts_with($sign, self::SIGNATURE_MARKER) ? [TrustStamp::trusted()] : [];

                if (!$failure instanceof MessageDecodingFailedException || $this->hasValidSignature($failure->encodedEnvelope, !$signAll)) {
                    if (!$signAll && $this->shouldSign($failure::class)) {
                        self::triggerSignedMessageTypeDeprecation($failure::class);
                    }

                    return $envelope->with(...$trust);
                }

                // the failed envelope is decoded again later, so it must keep its signature
                return MessageDecodingFailedException::wrap($encodedEnvelope, $failure->getMessage(), $failure->getCode(), $failure->getPrevious())->with(...array_merge(...array_values($envelope->all())), ...$trust);
            }

            if ($signAll) {
                if (!$signed || !$this->acceptUnverified) {
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

                // the type of a message signed as unverified is not read, as the handlers that require a signature refuse such a message
                $envelope = $this->inner->decode($signedEnvelope);

                if (!$envelope->getMessage() instanceof MessageDecodingFailedException) {
                    return $envelope->with(TrustStamp::untrusted());
                }

                // a claim reference it carries is verified only once retrieved: the stamps of this failure must not reach the signed message it resolves to
                return $envelope->with(new UnverifiedDecodingFailureStamp(array_merge(...array_values($envelope->all()))), TrustStamp::untrusted());
            }

            // a message signed as unverified is read like an unsigned one: the signature only tells that this key signed it as such
            $unverifiedEnvelope = $signed ? $signedEnvelope : $encodedEnvelope;
            $envelope = null;

            if (!$this->inner instanceof MessageTypeAwareSerializerInterface) {
                $envelope = $this->inner->decode($unverifiedEnvelope);
                $type = $envelope->getMessage()::class;
            } elseif (null === $type = $this->inner->getMessageType($unverifiedEnvelope, false)) {
                throw new InvalidMessageSignatureException('The message could not be verified and its type could not be determined; refusing to decode it.');
            }

            if (!$this->shouldSign($type)) {
                $envelope ??= $this->inner->decode($unverifiedEnvelope);
                $failure = $envelope->getMessage();
                $trust = $signed ? [TrustStamp::untrusted()] : [];

                if ($failure instanceof MessageDecodingFailedException) {
                    // encode() signs a failure that carries a signed envelope. An unsigned one is forged: it would attach unverified stamps to that envelope.
                    if (!$signed && $this->hasValidSignature($failure->encodedEnvelope, true)) {
                        throw new InvalidMessageSignatureException('The message is an unsigned decoding failure that carries a signed envelope; refusing to decode it.');
                    }

                    // a claim reference it carries is verified only once retrieved: the stamps of this failure must not reach the signed message it resolves to
                    return $envelope->with(new UnverifiedDecodingFailureStamp(array_merge(...array_values($envelope->all())), $this->signedMessageTypes), ...$trust);
                }

                // the class of the decoded message is checked too, as it can differ from the type read before decoding
                if (!$this->shouldSign($type = $envelope->getMessage()::class)) {
                    return $envelope->with(...$trust);
                }
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

    private static function triggerSignedMessageTypeDeprecation(string $type): void
    {
        trigger_deprecation('symfony/messenger', '8.2', 'Signing only the messages that require a signature, such as "%s", is deprecated. Set the "sign" option of their transport to sign every message. In 9.0, their handlers will refuse the messages of transports that do not sign every message.', $type);
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

        if (null === $payload = $bodyOnly ? $body : self::getSignedPayload($encodedEnvelope)) {
            return false;
        }

        return array_any($this->signingKeys, fn ($key) => hash_equals(($bodyOnly ? '' : self::SIGNATURE_MARKER).hash_hmac($this->algorithm, $payload, $this->getSigningKey($key, $bodyOnly)), $sign));
    }

    private function getSigningKey(#[\SensitiveParameter] string $key, bool $bodyOnly): string
    {
        return $bodyOnly ? $key : hash_hmac($this->algorithm, self::SIGNATURE_MARKER, $key);
    }

    /**
     * Returns the body and the signed headers as the payload the signature covers, or null when a signed header is missing.
     */
    private static function getSignedPayload(array $encodedEnvelope): ?string
    {
        if (null === $headers = self::getSignedHeaders($encodedEnvelope)) {
            return null;
        }

        // transports are free to reorder headers, so the order must not matter
        ksort($headers, \SORT_STRING);

        // serialize() gives an unambiguous encoding of the pair; it is never read back
        return serialize([$encodedEnvelope['body'] ?? '', $headers]);
    }

    /**
     * Returns the headers that a signature covering the headers covers, or null when a listed header is missing.
     *
     * The headers that describe the message are signed whenever present. Adding such a header breaks the signature, as before the list existed.
     * The list itself is signed, so that a signature with a list never matches a signature without a list.
     */
    private static function getSignedHeaders(array $encodedEnvelope): ?array
    {
        $headers = \is_array($encodedEnvelope['headers'] ?? null) ? $encodedEnvelope['headers'] : [];
        $signed = array_filter($headers, self::describesTheMessage(...), \ARRAY_FILTER_USE_KEY);

        if (!\array_key_exists(self::SIGNED_HEADERS, $headers)) {
            return $signed;
        }

        if (!\is_string($list = $headers[self::SIGNED_HEADERS]) || !\is_array($names = json_decode($list, true, 2)) || !array_is_list($names)) {
            return null;
        }

        $signed[self::SIGNED_HEADERS] = $list;

        foreach ($names as $name) {
            if (!\is_string($name) || !\array_key_exists($name, $headers)) {
                return null;
            }

            $signed[$name] = $headers[$name];
        }

        return $signed;
    }

    private static function describesTheMessage(string $name): bool
    {
        return 'type' === $name || self::UNVERIFIED_HEADER === $name || str_starts_with($name, self::STAMP_HEADER_PREFIX);
    }
}
