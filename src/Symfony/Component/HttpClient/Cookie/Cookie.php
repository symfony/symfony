<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Cookie;

use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Cookie\CookieInterface;

/**
 * Represents a single HTTP request cookie (name=value pair).
 *
 * @author Edouard Courty <edouard.courty2@gmail.com>
 */
final class Cookie implements CookieInterface
{
    /**
     * RFC 2616 separator characters illegal in a cookie-name, excluding CTLs and space
     * which are added dynamically by getIllegalNameCharacters().
     *
     * Ordinal values: " ( ) , / : ; < = > ? @ [ \ ] { } DEL
     */
    private const array ILLEGAL_NAME_CHARS = [
        34,           // "
        40, 41,       // ( )
        44,           // ,
        47,           // /
        58, 59,       // : ;
        60, 61, 62,   // < = >
        63, 64,       // ? @
        91, 92, 93,   // [ \ ]
        123, 125,     // { }
        127,          // DEL
    ];

    /**
     * Characters illegal in a cookie-value beyond CTLs and space:
     * double-quote, comma, semicolon, backslash, DEL, and non-ASCII bytes (0x80–0xFF).
     *
     * Ordinal values: " , ; \ DEL
     * (CTLs 0–32 and non-ASCII 128–255 are added dynamically by getIllegalValueCharacters().)
     */
    private const array ILLEGAL_VALUE_CHARS = [
        34,  // "
        44,  // ,
        59,  // ;
        92,  // \
        127, // DEL
    ];

    private static ?string $illegalNameCharsCache = null;
    private static ?string $illegalValueCharsCache = null;

    public function __construct(
        private readonly string $name,
        private readonly string $value = '',
    ) {
        if ('' === $name) {
            throw new InvalidArgumentException('Cookie name cannot be empty.');
        }

        if (strpbrk($name, self::getIllegalNameCharacters()) !== false) {
            throw new InvalidArgumentException(\sprintf('Invalid cookie name "%s": it contains illegal characters.', $name));
        }

        if (strpbrk($value, self::getIllegalValueCharacters()) !== false) {
            throw new InvalidArgumentException(\sprintf('Invalid cookie value for "%s": it must contain only printable US-ASCII characters excluding whitespace, double-quote, comma, semicolon and backslash (RFC 6265 §4.1.1). Use rawurlencode() if the value contains special characters.', $name));
        }
    }

    /**
     * Returns a string containing all characters illegal in a cookie name per RFC 6265.
     *
     * Cookie names are defined as RFC 2616 tokens: any US-ASCII character except
     * control characters (0x00–0x1F), space (0x20), DEL (0x7F), and the separator characters above.
     *
     * Can be used with strpbrk() to detect illegal characters in a custom name.
     */
    public static function getIllegalNameCharacters(): string
    {
        return self::$illegalNameCharsCache ??= implode('', array_map('chr', array_merge(
            range(0, 32), // CTLs (0x00–0x1F) and space (0x20)
            self::ILLEGAL_NAME_CHARS,
        )));
    }

    /**
     * Returns a string containing all characters illegal in a cookie value per RFC 6265 §4.1.1.
     *
     * Valid cookie-octets are printable US-ASCII (0x21–0x7E) excluding
     * double-quote (0x22), comma (0x2C), semicolon (0x3B), and backslash (0x5C).
     *
     * Can be used with strpbrk() to detect illegal characters in a custom value.
     */
    public static function getIllegalValueCharacters(): string
    {
        return self::$illegalValueCharsCache ??= implode('', array_map('chr', array_merge(
            range(0, 32),    // CTLs (0x00–0x1F) and space (0x20)
            self::ILLEGAL_VALUE_CHARS,
            range(128, 255), // non-ASCII bytes
        )));
    }

    /**
     * Creates a Cookie from a "name=value" string.
     */
    public static function fromString(string $cookie): self
    {
        [$name, $value] = explode('=', $cookie, 2) + [1 => ''];

        return new self(trim($name), trim($value));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->name . '=' . $this->value;
    }
}
