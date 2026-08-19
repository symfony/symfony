<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink;

use Psr\Link\EvolvableLinkProviderInterface;
use Symfony\Component\WebLink\Exception\InvalidArgumentException;

/**
 * Parses a list of HTTP Link-Template headers into a list of Link instances.
 *
 * As mandated by RFC 9651 for structured fields, a header value that cannot be
 * parsed is ignored as a whole and yields an empty list of links.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9652.html
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class LinkTemplateHeaderParser
{
    /**
     * @param string|string[] $headers Value of the "Link-Template" HTTP header
     */
    public function parse(string|array $headers): EvolvableLinkProviderInterface
    {
        if (\is_array($headers)) {
            $headers = implode(', ', $headers);
        }

        try {
            return self::parseList($headers);
        } catch (InvalidArgumentException) {
            return new GenericLinkProvider();
        }
    }

    private static function parseList(string $input): GenericLinkProvider
    {
        $links = new GenericLinkProvider();
        $offset = 0;
        self::skipSp($input, $offset);

        if ($offset >= \strlen($input)) {
            return $links;
        }

        while (true) {
            $href = match ($input[$offset] ?? null) {
                '"' => self::parseString($input, $offset),
                '%' => self::parseDisplayString($input, $offset),
                default => throw new InvalidArgumentException('A Link-Template member must be a structured field string.'),
            };

            $link = new Link(null, $href);

            foreach (self::parseParameters($input, $offset) as $key => $value) {
                if ('rel' === $key) {
                    if (\is_string($value)) {
                        foreach (preg_split('/\s+/', $value, 0, \PREG_SPLIT_NO_EMPTY) as $rel) {
                            $link = $link->withRel($rel);
                        }
                    }

                    continue;
                }

                $link = $link->withAttribute($key, $value);
            }

            $links = $links->withLink($link);

            self::skipOws($input, $offset);

            if ($offset >= \strlen($input)) {
                return $links;
            }

            if (',' !== $input[$offset]) {
                throw new InvalidArgumentException('Expected a comma between Link-Template members.');
            }

            ++$offset;
            self::skipOws($input, $offset);

            if ($offset >= \strlen($input)) {
                throw new InvalidArgumentException('The Link-Template header must not end with a trailing comma.');
            }
        }
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    private static function parseParameters(string $input, int &$offset): array
    {
        $parameters = [];

        while (';' === ($input[$offset] ?? null)) {
            ++$offset;
            self::skipSp($input, $offset);

            if (!preg_match('/[a-z*][a-z0-9_.*-]*/A', $input, $matches, 0, $offset)) {
                throw new InvalidArgumentException('Expected a structured field parameter key.');
            }

            $offset += \strlen($matches[0]);
            $value = true;

            if ('=' === ($input[$offset] ?? null)) {
                ++$offset;
                $value = self::parseBareItem($input, $offset);
            }

            $parameters[$matches[0]] = $value;
        }

        return $parameters;
    }

    private static function parseBareItem(string $input, int &$offset): string|int|float|bool
    {
        return match (true) {
            !isset($input[$offset]) => throw new InvalidArgumentException('Unexpected end of the Link-Template header.'),
            '"' === $input[$offset] => self::parseString($input, $offset),
            '%' === $input[$offset] => self::parseDisplayString($input, $offset),
            '?' === $input[$offset] => self::parseBoolean($input, $offset),
            '-' === $input[$offset] || ctype_digit($input[$offset]) => self::parseNumber($input, $offset),
            '*' === $input[$offset] || ctype_alpha($input[$offset]) => self::parseToken($input, $offset),
            default => throw new InvalidArgumentException(\sprintf('Unexpected "%s" character in the Link-Template header.', $input[$offset])),
        };
    }

    private static function parseString(string $input, int &$offset): string
    {
        ++$offset;
        $output = '';

        while (isset($input[$offset])) {
            $char = $input[$offset++];

            if ('\\' === $char) {
                if (!isset($input[$offset])) {
                    throw new InvalidArgumentException('Unterminated escape sequence in the Link-Template header.');
                }

                $next = $input[$offset++];

                if ('"' !== $next && '\\' !== $next) {
                    throw new InvalidArgumentException('Invalid escape sequence in the Link-Template header.');
                }

                $output .= $next;

                continue;
            }

            if ('"' === $char) {
                return $output;
            }

            if (0x20 > \ord($char) || 0x7E < \ord($char)) {
                throw new InvalidArgumentException('A structured field string must hold printable ASCII characters only.');
            }

            $output .= $char;
        }

        throw new InvalidArgumentException('Unterminated string in the Link-Template header.');
    }

    private static function parseDisplayString(string $input, int &$offset): string
    {
        if ('%"' !== substr($input, $offset, 2)) {
            throw new InvalidArgumentException('Expected a structured field display string.');
        }

        $offset += 2;
        $output = '';

        while (isset($input[$offset])) {
            $char = $input[$offset++];

            if (0x20 > \ord($char) || 0x7E < \ord($char)) {
                throw new InvalidArgumentException('A structured field display string must hold printable ASCII characters only.');
            }

            if ('%' === $char) {
                if (!preg_match('/[0-9a-f]{2}/A', $input, $matches, 0, $offset)) {
                    throw new InvalidArgumentException('Invalid percent-encoded byte in the Link-Template header.');
                }

                $offset += 2;
                $output .= \chr(hexdec($matches[0]));

                continue;
            }

            if ('"' === $char) {
                if (!preg_match('//u', $output)) {
                    throw new InvalidArgumentException('A structured field display string must be encoded in UTF-8.');
                }

                return $output;
            }

            $output .= $char;
        }

        throw new InvalidArgumentException('Unterminated display string in the Link-Template header.');
    }

    private static function parseBoolean(string $input, int &$offset): bool
    {
        if (!preg_match('/\?[01]/A', $input, $matches, 0, $offset)) {
            throw new InvalidArgumentException('Expected a structured field boolean.');
        }

        $offset += 2;

        return '?1' === $matches[0];
    }

    private static function parseNumber(string $input, int &$offset): int|float
    {
        if (!preg_match('/-?(?:\d{1,12}\.\d{1,3}|\d{1,15})(?![\d.])/A', $input, $matches, 0, $offset)) {
            throw new InvalidArgumentException('Expected a structured field number.');
        }

        $offset += \strlen($matches[0]);

        return str_contains($matches[0], '.') ? (float) $matches[0] : (int) $matches[0];
    }

    private static function parseToken(string $input, int &$offset): string
    {
        if (!preg_match('/[a-zA-Z*][a-zA-Z0-9:\/!#$%&\'*+.^_`|~-]*/A', $input, $matches, 0, $offset)) {
            throw new InvalidArgumentException('Expected a structured field token.');
        }

        $offset += \strlen($matches[0]);

        return $matches[0];
    }

    private static function skipSp(string $input, int &$offset): void
    {
        while (' ' === ($input[$offset] ?? null)) {
            ++$offset;
        }
    }

    private static function skipOws(string $input, int &$offset): void
    {
        while (' ' === ($input[$offset] ?? null) || "\t" === ($input[$offset] ?? null)) {
            ++$offset;
        }
    }
}
