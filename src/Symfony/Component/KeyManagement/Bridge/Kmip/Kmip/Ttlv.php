<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Kmip;

use Symfony\Component\KeyManagement\Bridge\Kmip\KmipVersion;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Encodes and decodes the binary Tag, Type, Length, Value (TTLV) items carried in KMIP messages.
 *
 * KMIP represents request and response fields as TTLV items.
 * Each item has a three-byte numeric Tag identifying the protocol field or object, a one-byte Type identifying the Value's data format, and a four-byte Length giving the Value's size in bytes.
 * The Value contains data such as an integer or byte string, or nested TTLV items when the Type is Structure.
 * Items are aligned to eight bytes: trailing padding is outside a non-Structure item's Length, while a Structure's Length includes the padding of its nested items.
 *
 * Encoding pads items to that alignment.
 * Decoding checks item boundaries, tag prefixes, type lengths, Boolean values and UTF-8 text before returning the raw item values.
 *
 * The decoder accepts the Date Time Extended type only for KMIP 2.0 and 2.1. It checks TTLV syntax; {@see ProtocolClient} checks the meaning and order of fields in KMIP responses.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
final class Ttlv
{
    /**
     * Maximum accepted frame size in bytes, imposed by this bridge.
     */
    public const int MAX_FRAME = 16 * 1024 * 1024;
    public const int STRUCTURE = 1;
    public const int INTEGER = 2;
    public const int LONG_INTEGER = 3;
    public const int BIG_INTEGER = 4;
    public const int ENUMERATION = 5;
    public const int BOOLEAN = 6;
    public const int TEXT = 7;
    public const int BYTES = 8;
    public const int DATE_TIME = 9;
    public const int INTERVAL = 10;
    /**
     * Signed microseconds since the Unix epoch, introduced in KMIP 2.0.
     */
    public const int DATE_TIME_EXTENDED = 11;

    private const int ITEM_HEADER_LENGTH = 8;
    private const int ITEM_ALIGNMENT = 8;
    private const int TAG_LENGTH = 3;
    /**
     * Moves the tag's first byte into the low bits for prefix validation.
     */
    private const int TAG_PREFIX_SHIFT_BITS = 16;
    /**
     * First byte of a standard KMIP tag.
     */
    private const int STANDARD_TAG_PREFIX = 0x42;
    /**
     * First byte of a KMIP extension tag.
     */
    private const int EXTENSION_TAG_PREFIX = 0x54;
    /**
     * Reserved tag value, which cannot identify an item.
     */
    private const int UNUSED_TAG = 0x420000;
    private const int INTEGER_LENGTH = 4;
    private const int MAX_SIGNED_INTEGER = 2147483647;
    private const int MIN_SIGNED_INTEGER = ~self::MAX_SIGNED_INTEGER;
    /**
     * The largest unsigned 32-bit value is a float literal because it exceeds 32-bit PHP integers.
     */
    private const float MAX_UNSIGNED_INTEGER = 4294967295.0;
    private const int HIGH_WORD_INDEX = 1;
    private const int LOW_WORD_INDEX = 2;
    private const int WORD_BITS = 16;
    private const int SIGN_BIT_IN_HIGH_WORD = 0x8000;
    private const int WORD_MODULUS = 0x10000;
    private const int LONG_INTEGER_LENGTH = 8;
    /**
     * PHP unpack() numbers unnamed values starting at one.
     */
    private const int UNPACKED_NUMBER_INDEX = 1;
    /**
     * TTLV encodes Boolean false as eight zero bytes.
     */
    private const string BOOLEAN_FALSE = "\0\0\0\0\0\0\0\0";
    /**
     * TTLV encodes Boolean true as seven zero bytes followed by one.
     */
    private const string BOOLEAN_TRUE = "\0\0\0\0\0\0\0\1";
    /**
     * PHP pack()/unpack() format for an unsigned 32-bit integer in network byte order.
     */
    private const string UINT32_BIG_ENDIAN_FORMAT = 'N';
    /**
     * Two unsigned 16-bit words in network byte order avoid signed unpacking of 32-bit values.
     */
    private const string UINT16_PAIR_BIG_ENDIAN_FORMAT = 'n2';
    /**
     * Extends a three-byte KMIP tag to four bytes for unpacking.
     */
    private const string TAG_UNPACK_PREFIX = "\0";
    /**
     * TTLV item padding is zero filled by the encoder.
     */
    private const string PADDING_BYTE = "\0";
    /**
     * An empty regular expression in Unicode mode checks UTF-8 validity.
     */
    private const string UTF8_VALIDATION_PATTERN = '//u';
    /**
     * Hexadecimal input preserves the full unsigned 32-bit range on 32-bit PHP.
     */
    private const string UNSIGNED_HEX_PATTERN = '/^0x[0-9A-Fa-f]{1,8}$/D';

    public static function encode(int $tag, int $type, #[\SensitiveParameter] string $value): string
    {
        $length = \strlen($value);
        if ($length > self::MAX_FRAME - self::ITEM_HEADER_LENGTH) {
            throw new FrameTooLarge('KMIP item exceeds the maximum frame size.');
        }

        return substr(pack(self::UINT32_BIG_ENDIAN_FORMAT, $tag), -self::TAG_LENGTH).\chr($type).pack(self::UINT32_BIG_ENDIAN_FORMAT, $length).$value.str_repeat(self::PADDING_BYTE, (self::ITEM_ALIGNMENT - $length % self::ITEM_ALIGNMENT) % self::ITEM_ALIGNMENT);
    }

    public static function structure(int $tag, #[\SensitiveParameter] string $children): string
    {
        return self::encode($tag, self::STRUCTURE, $children);
    }

    /**
     * Encodes a signed Integer or an unsigned Enumeration or Interval.
     *
     * A hexadecimal string can express the full unsigned range on 32-bit PHP.
     */
    public static function integer(int $tag, int|string $value, int $type = self::INTEGER): string
    {
        if (\is_string($value)) {
            if (!\in_array($type, [self::ENUMERATION, self::INTERVAL], true) || !preg_match(self::UNSIGNED_HEX_PATTERN, $value)) {
                throw new RuntimeException('KMIP unsigned numeric value is outside the 32-bit range.');
            }

            return self::encode($tag, $type, pack('H*', str_pad(substr($value, 2), self::INTEGER_LENGTH * 2, '0', \STR_PAD_LEFT)));
        }

        if (self::INTEGER === $type) {
            if ($value < self::MIN_SIGNED_INTEGER || $value > self::MAX_SIGNED_INTEGER) {
                throw new RuntimeException('KMIP Integer is outside the signed 32-bit range.');
            }
        } elseif (!\in_array($type, [self::ENUMERATION, self::INTERVAL], true) || $value < 0 || $value > self::MAX_UNSIGNED_INTEGER) {
            throw new RuntimeException('KMIP unsigned numeric value is outside the 32-bit range.');
        }

        return self::encode($tag, $type, pack(self::UINT32_BIG_ENDIAN_FORMAT, $value));
    }

    public static function text(int $tag, #[\SensitiveParameter] string $value): string
    {
        if (!preg_match(self::UTF8_VALIDATION_PATTERN, $value)) {
            throw new RuntimeException('Invalid KMIP UTF-8 text string.');
        }

        return self::encode($tag, self::TEXT, $value);
    }

    public static function bytes(int $tag, #[\SensitiveParameter] string $value): string
    {
        return self::encode($tag, self::BYTES, $value);
    }

    /**
     * @return list<array{tag: int, type: int, value: string}>
     */
    public static function items(#[\SensitiveParameter] string $data, KmipVersion $version): array
    {
        $items = [];
        $offset = 0;
        $total = \strlen($data);
        while ($offset < $total) {
            if ($total - $offset < self::ITEM_HEADER_LENGTH) {
                throw new RuntimeException('Truncated KMIP item header.');
            }

            $tag = unpack(self::UINT32_BIG_ENDIAN_FORMAT, self::TAG_UNPACK_PREFIX.substr($data, $offset, self::TAG_LENGTH))[self::UNPACKED_NUMBER_INDEX];
            $type = \ord($data[$offset + self::TAG_LENGTH]);
            $length = unpack(self::UINT32_BIG_ENDIAN_FORMAT, substr($data, $offset + self::INTEGER_LENGTH, self::INTEGER_LENGTH))[self::UNPACKED_NUMBER_INDEX];
            if ($length < 0 || $length > self::MAX_FRAME - self::ITEM_HEADER_LENGTH || $length > $total - $offset - self::ITEM_HEADER_LENGTH || ($padded = $length + (self::ITEM_ALIGNMENT - $length % self::ITEM_ALIGNMENT) % self::ITEM_ALIGNMENT) > $total - $offset - self::ITEM_HEADER_LENGTH) {
                throw new RuntimeException('Invalid KMIP item length or padding.');
            }
            if (!\in_array($tag >> self::TAG_PREFIX_SHIFT_BITS, [self::STANDARD_TAG_PREFIX, self::EXTENSION_TAG_PREFIX], true) || self::UNUSED_TAG === $tag || $type < self::STRUCTURE || $type > (KmipVersion::Version14 === $version ? self::INTERVAL : self::DATE_TIME_EXTENDED)) {
                throw new RuntimeException('Invalid KMIP tag or type.');
            }
            if ((\in_array($type, [self::STRUCTURE, self::BIG_INTEGER], true) && 0 !== $length % self::ITEM_ALIGNMENT)
                || (\in_array($type, [self::INTEGER, self::ENUMERATION, self::INTERVAL], true) && self::INTEGER_LENGTH !== $length)
                || (\in_array($type, [self::LONG_INTEGER, self::BOOLEAN, self::DATE_TIME, self::DATE_TIME_EXTENDED], true) && self::LONG_INTEGER_LENGTH !== $length)) {
                throw new RuntimeException('Invalid KMIP item length.');
            }

            $value = substr($data, $offset + self::ITEM_HEADER_LENGTH, $length);
            if (self::BOOLEAN === $type && !\in_array($value, [self::BOOLEAN_FALSE, self::BOOLEAN_TRUE], true)) {
                throw new RuntimeException('Invalid KMIP Boolean value.');
            }
            if (self::TEXT === $type && !preg_match(self::UTF8_VALIDATION_PATTERN, $value)) {
                throw new RuntimeException('Invalid KMIP UTF-8 text string.');
            }

            $items[] = ['tag' => $tag, 'type' => $type, 'value' => $value];
            $offset += self::ITEM_HEADER_LENGTH + $padded;
        }

        return $items;
    }

    /**
     * @param array{tag: int, type: int, value: string} $item
     */
    public static function requireType(#[\SensitiveParameter] array $item, int $tag, int $type): string
    {
        if ($item['tag'] !== $tag || $item['type'] !== $type) {
            throw new RuntimeException('Unexpected KMIP tag or type.');
        }

        return $item['value'];
    }

    /**
     * Decodes a numeric item, preserving unsigned values beyond PHP_INT_MAX as hexadecimal strings on 32-bit PHP.
     *
     * @param array{tag: int, type: int, value: string} $item
     */
    public static function number(array $item, int $tag, int $type = self::INTEGER): int|string
    {
        if (!\in_array($type, [self::INTEGER, self::ENUMERATION, self::INTERVAL], true)) {
            throw new RuntimeException('Invalid KMIP numeric type.');
        }
        $value = self::requireType($item, $tag, $type);
        if (self::INTEGER_LENGTH !== \strlen($value)) {
            throw new RuntimeException('Invalid KMIP numeric field.');
        }

        $words = unpack(self::UINT16_PAIR_BIG_ENDIAN_FORMAT, $value);
        $high = $words[self::HIGH_WORD_INDEX];
        $low = $words[self::LOW_WORD_INDEX];
        if ($high < self::SIGN_BIT_IN_HIGH_WORD) {
            return ($high << self::WORD_BITS) | $low;
        }
        if (self::INTEGER === $type) {
            return ($high - self::WORD_MODULUS) * self::WORD_MODULUS + $low;
        }

        return 4 === \PHP_INT_SIZE ? '0x'.strtoupper(bin2hex($value)) : ($high << self::WORD_BITS) | $low;
    }

    /**
     * @param array{tag: int, type: int, value: string} $item
     *
     * @return list<array{tag: int, type: int, value: string}>
     */
    public static function children(#[\SensitiveParameter] array $item, int $tag, KmipVersion $version): array
    {
        return self::items(self::requireType($item, $tag, self::STRUCTURE), $version);
    }
}
