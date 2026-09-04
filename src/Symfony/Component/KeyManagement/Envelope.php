<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement;

use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Envelope produced by {@see EnvelopeEncrypter}.
 *
 * Carries what is needed to decrypt a payload protected with envelope encryption: the AEAD nonce
 * and tag, the local ciphertext, and how to obtain the data key. The bytes are owned by its
 * {@see EnvelopeFormat}, so callers persist or transmit the object as opaque bytes through
 * {@see __toString()} and read it back through {@see fromBytes()}, which resolves the format from
 * the leading byte.
 *
 * The data key comes in one of two shapes, decided by the format and never mixed. Either the
 * envelope carries its wrapped data key ({@see selfContained()}, laid out by
 * {@see SelfContainedFormat}), or it refers to one held by a {@see DataKeyStoreInterface}
 * ({@see referencing()}, laid out by {@see StoredFormat}). Each factory writes the format that
 * matches its shape, so the two cannot be crossed.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class Envelope implements \Stringable
{
    private const int MAX_FIELD_BYTES = 0xFFFF;

    private function __construct(
        public readonly EnvelopeFormat $format,
        public readonly string $iv,
        public readonly string $tag,
        public readonly string $ciphertext,
        public readonly ?string $keyId = null,
        public readonly ?string $wrappedDek = null,
        public readonly ?string $reference = null,
    ) {
    }

    /**
     * An envelope carrying its own wrapped data key.
     *
     * @throws InvalidArgumentException If `$keyId` or `$wrappedDek` overflow their on-the-wire length fields, or if `$iv` or `$tag` do not have the length the format frames them at
     */
    public static function selfContained(string $keyId, string $wrappedDek, string $iv, string $tag, string $ciphertext): self
    {
        self::assertFits('key id', $keyId);
        self::assertFits('wrapped data key', $wrappedDek);
        $format = new SelfContainedFormat();
        self::assertLength('iv', $iv, $format->ivBytes());
        self::assertLength('tag', $tag, $format->tagBytes());

        return new self($format, $iv, $tag, $ciphertext, keyId: $keyId, wrappedDek: $wrappedDek);
    }

    /**
     * An envelope referring to a data key held by a {@see DataKeyStoreInterface}.
     *
     * @throws InvalidArgumentException If `$reference` overflows its on-the-wire length field, or if `$iv` or `$tag` do not have the length the format frames them at
     */
    public static function referencing(string $reference, string $iv, string $tag, string $ciphertext): self
    {
        self::assertFits('data key reference', $reference);
        $format = new StoredFormat();
        self::assertLength('iv', $iv, $format->ivBytes());
        self::assertLength('tag', $tag, $format->tagBytes());

        return new self($format, $iv, $tag, $ciphertext, reference: $reference);
    }

    public function __toString(): string
    {
        return $this->format->frame($this);
    }

    /**
     * @throws InvalidArgumentException If the frame is malformed or its format is not supported
     */
    public static function fromBytes(string $bytes): self
    {
        if ('' === $bytes) {
            throw new InvalidArgumentException('Invalid envelope frame.');
        }

        return EnvelopeFormat::fromId(\ord($bytes[0]))->parse($bytes);
    }

    /**
     * @throws InvalidArgumentException If the value overflows its 2-byte on-the-wire length field
     */
    private static function assertFits(string $name, string $value): void
    {
        if (\strlen($value) > self::MAX_FIELD_BYTES) {
            throw new InvalidArgumentException(\sprintf('Envelope "%s" is too long: max %d bytes, %d given.', $name, self::MAX_FIELD_BYTES, \strlen($value)));
        }
    }

    /**
     * The iv and tag are framed without a length prefix, so a value of the wrong length would
     * frame fine and reparse shifted.
     *
     * @throws InvalidArgumentException If the value does not have the length the format frames it at
     */
    private static function assertLength(string $name, string $value, int $expectedBytes): void
    {
        if (\strlen($value) !== $expectedBytes) {
            throw new InvalidArgumentException(\sprintf('Envelope "%s" must be %d bytes long, %d given.', $name, $expectedBytes, \strlen($value)));
        }
    }
}
