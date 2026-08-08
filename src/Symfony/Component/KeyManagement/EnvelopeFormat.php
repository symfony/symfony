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
 * How an {@see Envelope} is laid out on the wire, and with which AEAD parameters.
 *
 * A format owns one byte layout: it writes a frame from an envelope and reads an envelope back
 * from a frame. The leading byte of every frame is its {@see id()}, so a reader resolves the
 * format before parsing anything else, through {@see fromId()}.
 *
 * Formats describe bytes and nothing else. They never resolve a data key, because that would turn
 * a value object into a service and force one instance per configured backend. Obtaining the key
 * is the encrypter's job.
 *
 * Ids are a wire contract: once a format ships, its id and its layout are frozen, and a change of
 * cipher, of lengths or of layout is a new format with a new id rather than an edit to an existing
 * one.
 *
 * Nor are formats a configuration point. Each encrypter owns the one matching its shape, and
 * {@see fromId()} resolves nothing but the formats shipped here, so a payload written anywhere
 * reads back anywhere. A new layout is therefore added to the component, with its own id, rather
 * than plugged in by an application.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
abstract class EnvelopeFormat
{
    /**
     * @return int<0, 255> the leading byte of every frame in this format
     */
    abstract public function id(): int;

    /**
     * @return positive-int length of the data encryption key in bytes
     */
    abstract public function keyBytes(): int;

    /**
     * @return positive-int length of the AEAD nonce/IV in bytes
     */
    abstract public function ivBytes(): int;

    /**
     * @return positive-int length of the AEAD tag in bytes
     */
    abstract public function tagBytes(): int;

    /**
     * @return non-empty-string cipher name accepted by `openssl_encrypt()`
     */
    abstract public function cipher(): string;

    abstract public function frame(Envelope $envelope): string;

    /**
     * @throws InvalidArgumentException If the frame is malformed
     */
    abstract public function parse(string $bytes): Envelope;

    /**
     * @throws InvalidArgumentException If no shipped format claims `$id`
     */
    final public static function fromId(int $id): self
    {
        return match ($id) {
            SelfContainedFormat::ID => new SelfContainedFormat(),
            default => throw new InvalidArgumentException(\sprintf('Unsupported envelope format (id 0x%02x).', $id)),
        };
    }

    /**
     * Writes the leading id byte followed by each field prefixed with its 2-byte big-endian length.
     */
    final protected function head(string ...$fields): string
    {
        $head = \chr($this->id());
        foreach ($fields as $field) {
            $head .= pack('n', \strlen($field)).$field;
        }

        return $head;
    }

    /**
     * @throws InvalidArgumentException If the frame is too short to hold the announced field
     */
    final protected static function readField(string $bytes, int &$offset, int $length): string
    {
        if ($offset + 2 > $length) {
            throw new InvalidArgumentException('Invalid envelope frame.');
        }

        $fieldLength = (int) unpack('n', substr($bytes, $offset, 2))[1];
        $offset += 2;

        if ($offset + $fieldLength > $length) {
            throw new InvalidArgumentException('Invalid envelope frame.');
        }

        $field = substr($bytes, $offset, $fieldLength);
        $offset += $fieldLength;

        return $field;
    }

    /**
     * Reads the nonce, the tag and the ciphertext that close every frame.
     *
     * @return array{string, string, string}
     *
     * @throws InvalidArgumentException If the frame is too short to hold the nonce and the tag
     */
    final protected function readTail(string $bytes, int $offset, int $length): array
    {
        if ($offset + $this->ivBytes() + $this->tagBytes() > $length) {
            throw new InvalidArgumentException('Invalid envelope frame.');
        }

        $iv = substr($bytes, $offset, $this->ivBytes());
        $offset += $this->ivBytes();
        $tag = substr($bytes, $offset, $this->tagBytes());
        $offset += $this->tagBytes();

        return [$iv, $tag, substr($bytes, $offset)];
    }
}
