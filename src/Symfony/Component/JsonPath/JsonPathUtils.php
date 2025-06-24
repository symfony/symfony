<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath;

use Symfony\Component\JsonPath\Exception\InvalidArgumentException;
use Symfony\Component\JsonPath\Tokenizer\JsonPathToken;
use Symfony\Component\JsonPath\Tokenizer\TokenType;
use Symfony\Component\JsonStreamer\Read\Splitter;

/**
 * Get the smallest deserializable JSON string from a list of tokens that doesn't need any processing.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class JsonPathUtils
{
    /**
     * @param JsonPathToken[] $tokens
     * @param resource        $json
     *
     * @return array{json: string, tokens: list<JsonPathToken>}
     */
    public static function findSmallestDeserializableStringAndPath(array $tokens, mixed $json): array
    {
        if (!\is_resource($json)) {
            throw new InvalidArgumentException('The JSON parameter must be a resource.');
        }

        $currentOffset = 0;
        $currentLength = null;

        $remainingTokens = $tokens;
        rewind($json);

        foreach ($tokens as $token) {
            $boundaries = [];

            if (TokenType::Name === $token->type) {
                foreach (Splitter::splitDict($json, $currentOffset, $currentLength) as $key => $bound) {
                    $boundaries[$key] = $bound;
                    if ($key === $token->value) {
                        break;
                    }
                }
            } elseif (TokenType::Bracket === $token->type && preg_match('/^\d+$/', $token->value)) {
                foreach (Splitter::splitList($json, $currentOffset, $currentLength) as $key => $bound) {
                    $boundaries[$key] = $bound;
                    if ($key === $token->value) {
                        break;
                    }
                }
            }

            if (!$boundaries) {
                // in case of a recursive descent or a filter, we can't reduce the JSON string
                break;
            }

            if (!\array_key_exists($token->value, $boundaries) || \count($remainingTokens) <= 1) {
                // the key given in the path is not found by the splitter or there is no remaining token to shift
                break;
            }

            // boundaries for the current token are found, we can remove it from the list
            // and substring the JSON string later
            $currentOffset = $boundaries[$token->value][0];
            $currentLength = $boundaries[$token->value][1];

            array_shift($remainingTokens);
        }

        return [
            'json' => stream_get_contents($json, $currentLength, $currentOffset ?: -1),
            'tokens' => $remainingTokens,
        ];
    }

    public static function unescapeString(string $str, string $quoteChar): string
    {
        if ('"' === $quoteChar) {
            // try JSON decoding first for unicode sequences
            $jsonStr = '"'.$str.'"';
            $decoded = json_decode($jsonStr, true);

            if (null !== $decoded) {
                return $decoded;
            }
        }

        $result = '';
        $i = -1;

        while (null !== $char = $str[++$i] ?? null) {
            if ('\\' === $char && isset($str[$i + 1])) {
                $result .= match ($str[$i + 1]) {
                    '"' => '"',
                    "'" => "'",
                    '\\' => '\\',
                    '/' => '/',
                    'b' => "\b",
                    'f' => "\f",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'u' => self::unescapeUnicodeSequence($str, $i),
                    default => $char.$str[$i + 1], // keep the backslash
                };

                ++$i;
            } else {
                $result .= $char;
            }
        }

        return $result;
    }

    private static function unescapeUnicodeSequence(string $str, int &$i): string
    {
        if (!isset($str[$i + 5])) {
            // not enough characters for Unicode escape, treat as literal
            return $str[$i];
        }

        $hex = substr($str, $i + 2, 4);
        if (!ctype_xdigit($hex)) {
            // invalid hex, treat as literal
            return $str[$i];
        }

        $codepoint = hexdec($hex);
        // looks like a valid Unicode codepoint, string length is sufficient and it starts with \u
        if (0xD800 <= $codepoint && $codepoint <= 0xDBFF && isset($str[$i + 11]) && '\\' === $str[$i + 6] && 'u' === $str[$i + 7]) {
            $lowHex = substr($str, $i + 8, 4);
            if (ctype_xdigit($lowHex)) {
                $lowSurrogate = hexdec($lowHex);
                if (0xDC00 <= $lowSurrogate && $lowSurrogate <= 0xDFFF) {
                    $codepoint = 0x10000 + (($codepoint & 0x3FF) << 10) + ($lowSurrogate & 0x3FF);
                    $i += 10; // skip surrogate pair

                    return mb_chr($codepoint, 'UTF-8');
                }
            }
        }

        // single Unicode character or invalid surrogate, skip the sequence
        $i += 4;

        return mb_chr($codepoint, 'UTF-8');
    }

    /**
     * @see https://datatracker.ietf.org/doc/rfc9535/, section 2.1.1
     */
    public static function normalizeWhitespace(string $input): string
    {
        $normalized = strtr($input, [
            "\t" => ' ',
            "\n" => ' ',
            "\r" => ' ',
        ]);

        return trim($normalized);
    }

    /**
     * Check a number is RFC 9535 compliant using strict JSON number format.
     */
    public static function isJsonNumber(string $value): bool
    {
        return (bool)preg_match('/^-?(0|[1-9]\d*)(\.\d+)?([eE][+-]?\d+)?$/', $value);
    }

    public static function parseCommaSeparatedValues(string $expr): array
    {
        $parts = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $bracketDepth = 0;
        $i = -1;

        while (null !== $char = $expr[++$i] ?? null) {
            if ('\\' === $char && isset($expr[$i + 1])) {
                $current .= $char.$expr[++$i];
                continue;
            }

            if ('"' === $char || "'" === $char) {
                if (!$inQuotes) {
                    $inQuotes = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                }
            } elseif (!$inQuotes) {
                if ('[' === $char) {
                    ++$bracketDepth;
                } elseif (']' === $char) {
                    --$bracketDepth;
                } elseif (0 === $bracketDepth && ',' === $char) {
                    $parts[] = trim($current);
                    $current = '';

                    continue;
                }
            }

            $current .= $char;
        }

        if ('' !== $current) {
            $parts[] = trim($current);
        }

        return $parts;
    }
}
