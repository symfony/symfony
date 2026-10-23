<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Read;

use Symfony\Component\JsonStreamer\Exception\InvalidStreamException;
use Symfony\Component\JsonStreamer\Exception\RuntimeException;

/**
 * Splits collections to retrieve the offset and length of each element.
 *
 * Only the structure of the split collection is validated. Elements are validated when decoded.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class Splitter
{
    private const CHUNK_LENGTH = 65536;
    private const MAX_DEPTH = 512;
    private const WHITESPACES = " \t\n\r";
    private const DELIMITERS = " \t\n\r\"{}[],:";

    private const EXPECT_VALUE = 1;
    private const EXPECT_VALUE_OR_END = 2;
    private const EXPECT_KEY = 3;
    private const EXPECT_KEY_OR_END = 4;
    private const EXPECT_COLON = 5;
    private const EXPECT_SEPARATOR = 6;
    private const EXPECT_END = 7;

    /**
     * @param resource $stream
     *
     * @return \Iterator<int, array{0: int, 1: int}>|null
     */
    public static function splitList($stream, int $offset = 0, ?int $length = null): ?\Iterator
    {
        return self::split($stream, $offset, $length, false);
    }

    /**
     * @param resource $stream
     *
     * @return \Iterator<array-key, array{0: int, 1: int}>|null
     */
    public static function splitCollection($stream, int $offset = 0, ?int $length = null): ?\Iterator
    {
        return self::split($stream, $offset, $length, null);
    }

    /**
     * @param resource $stream
     *
     * @return \Iterator<string, array{0: int, 1: int}>|null
     */
    public static function splitDict($stream, int $offset = 0, ?int $length = null): ?\Iterator
    {
        return self::split($stream, $offset, $length, true);
    }

    /**
     * @param resource $stream
     */
    private static function split($stream, int $offset, ?int $length, ?bool $dict): ?\Iterator
    {
        // non-seekable streams cannot be read twice, so their root is checked eagerly
        if (stream_get_meta_data($stream)['seekable']) {
            $opening = self::getFirstChar($stream, $offset, $length);
            $dict ??= '{' === $opening;

            if (($dict ? '{' : '[') === $opening) {
                return self::createBoundaries($stream, $offset, $length, $dict);
            }
        }

        $scan = self::scan($stream, $offset, $length);
        $opening = $scan->current();

        if ('null' === $opening) {
            $scan->next();

            return null;
        }

        $dict ??= '{' === $opening;

        if (($dict ? '{' : '[') !== $opening) {
            throw new InvalidStreamException('JSON is not valid.');
        }

        return self::createBoundaries($stream, $offset, $length, $dict, $scan);
    }

    /**
     * @param resource                                                      $stream
     * @param \Generator<array-key|null, string|array{0: int, 1: int}>|null $scan
     *
     * @return \Iterator<array-key, array{0: int, 1: int}>
     */
    private static function createBoundaries($stream, int $offset, ?int $length, bool $dict, ?\Generator $scan = null): \Iterator
    {
        if (null === $scan) {
            $scan = self::scan($stream, $offset, $length);

            // the stream may have changed since it has been peeked
            if (($dict ? '{' : '[') !== $scan->current()) {
                throw new InvalidStreamException('JSON is not valid.');
            }
        }

        for ($scan->next(); $scan->valid(); $scan->next()) {
            if ($dict) {
                yield $scan->key() => $scan->current();
            } else {
                yield $scan->current();
            }
        }
    }

    /**
     * @param resource $stream
     */
    private static function getFirstChar($stream, int $offset, ?int $length): ?string
    {
        while (null === $length || $length > 0) {
            if (false === $chunk = stream_get_contents($stream, min(64, $length ?? 64), $offset)) {
                throw new RuntimeException('Failed to read JSON stream.');
            }

            if ('' === $chunk) {
                return null;
            }

            if (($i = strspn($chunk, self::WHITESPACES)) < \strlen($chunk)) {
                return $chunk[$i];
            }

            $offset += $i;
            $length = null === $length ? null : $length - $i;
        }

        return null;
    }

    /**
     * Yields the root token first, then the boundaries of the root collection elements.
     *
     * @param resource $stream
     *
     * @return \Generator<array-key|null, string|array{0: int, 1: int}>
     */
    private static function scan($stream, int $offset, ?int $length): \Generator
    {
        $depth = 0;
        $expected = self::EXPECT_VALUE;
        $inString = $escaping = false;
        $dict = false;
        $rootToken = $rawKey = $key = null;
        $keys = $closers = [];
        $valueStart = 0;

        foreach (self::getChunks($stream, $offset, $length) as $chunkOffset => $chunk) {
            $chunkLength = \strlen($chunk);
            $i = 0;

            while ($i < $chunkLength) {
                if ($inString) {
                    if ($escaping) {
                        if (null !== $rawKey) {
                            $rawKey .= $chunk[$i];
                        }

                        $escaping = false;
                        ++$i;

                        continue;
                    }

                    $span = strcspn($chunk, '"\\', $i);
                    if (null !== $rawKey) {
                        $rawKey .= substr($chunk, $i, $span);
                    }

                    if (($i += $span) >= $chunkLength) {
                        break;
                    }

                    if ('\\' === $chunk[$i++]) {
                        if (null !== $rawKey) {
                            $rawKey .= '\\';
                        }

                        $escaping = true;

                        continue;
                    }

                    $inString = false;

                    if (null !== $rawKey) {
                        $key = self::decodeKey($rawKey);

                        if (isset($keys[$key])) {
                            throw new InvalidStreamException(\sprintf('Got "%s" dict key twice.', $rawKey));
                        }

                        $keys[$key] = true;
                        $rawKey = null;
                        $expected = self::EXPECT_COLON;
                    }

                    continue;
                }

                if (null !== $rootToken) {
                    $span = strcspn($chunk, self::DELIMITERS, $i);
                    $rootToken .= substr($chunk, $i, $span);

                    // the only valid scalar root for a collection is "null"
                    if (\strlen($rootToken) > 4) {
                        throw new InvalidStreamException('JSON is not valid.');
                    }

                    if (($i += $span) >= $chunkLength) {
                        break;
                    }

                    yield $rootToken;

                    $rootToken = null;
                    $expected = self::EXPECT_END;

                    continue;
                }

                if ($depth > 1) {
                    if (($i += strcspn($chunk, '"{}[]', $i)) >= $chunkLength) {
                        break;
                    }
                } elseif (1 === $depth) {
                    $span = strcspn($chunk, '"{}[],:', $i);

                    if ($span && ($whitespaces = strspn($chunk, self::WHITESPACES, $i, $span)) < $span) {
                        $start = $i + $whitespaces;

                        if (self::EXPECT_VALUE === $expected || self::EXPECT_VALUE_OR_END === $expected) {
                            $valueStart = $chunkOffset + $start;
                            $expected = self::EXPECT_SEPARATOR;
                        } elseif (self::EXPECT_KEY === $expected || self::EXPECT_KEY_OR_END === $expected) {
                            throw new InvalidStreamException(\sprintf('Expected dict key, but got "%s".', self::getToken($chunk, $start)));
                        } elseif (self::EXPECT_SEPARATOR !== $expected) {
                            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', self::getToken($chunk, $start)));
                        }
                    }

                    if (($i += $span) >= $chunkLength) {
                        break;
                    }
                } else {
                    if (($i += strspn($chunk, self::WHITESPACES, $i)) >= $chunkLength) {
                        break;
                    }

                    if (false === strpbrk($chunk[$i], '"{}[],:')) {
                        if (self::EXPECT_END === $expected) {
                            throw new InvalidStreamException(\sprintf('Expected end, but got "%s".', self::getToken($chunk, $i)));
                        }

                        $rootToken = '';

                        continue;
                    }
                }

                $char = $chunk[$i];
                $position = $chunkOffset + $i++;

                if ($depth > 1) {
                    if ('"' === $char) {
                        $inString = true;
                    } elseif ('{' !== $char && '[' !== $char) {
                        if (array_pop($closers) !== $char) {
                            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $char));
                        }

                        --$depth;
                    } elseif (++$depth >= self::MAX_DEPTH) {
                        throw new InvalidStreamException(\sprintf('Maximum stack depth of %d exceeded.', self::MAX_DEPTH));
                    } else {
                        $closers[] = '{' === $char ? '}' : ']';
                    }

                    continue;
                }

                if (0 === $depth) {
                    if (self::EXPECT_END === $expected && '"' === $char) {
                        throw new InvalidStreamException(\sprintf('Expected end, but got "%s".', self::getToken($chunk, $i - 1)));
                    }

                    if (self::EXPECT_END === $expected || !\in_array($char, ['{', '[', '"'], true)) {
                        throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $char));
                    }

                    yield $char;

                    if ('"' === $char) {
                        return;
                    }

                    $depth = 1;
                    $dict = '{' === $char;
                    $expected = $dict ? self::EXPECT_KEY_OR_END : self::EXPECT_VALUE_OR_END;

                    continue;
                }

                switch ($char) {
                    case '"':
                        if (self::EXPECT_KEY === $expected || self::EXPECT_KEY_OR_END === $expected) {
                            $rawKey = '';
                        } elseif (self::EXPECT_VALUE === $expected || self::EXPECT_VALUE_OR_END === $expected) {
                            $valueStart = $position;
                            $expected = self::EXPECT_SEPARATOR;
                        } else {
                            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', self::getToken($chunk, $i - 1)));
                        }

                        $inString = true;

                        break;

                    case '{':
                    case '[':
                        if (self::EXPECT_VALUE !== $expected && self::EXPECT_VALUE_OR_END !== $expected) {
                            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $char));
                        }

                        $valueStart = $position;
                        $expected = self::EXPECT_SEPARATOR;
                        $depth = 2;
                        $closers = ['{' === $char ? '}' : ']'];

                        break;

                    case '}':
                    case ']':
                        if (($dict ? '}' : ']') !== $char || !\in_array($expected, [self::EXPECT_SEPARATOR, self::EXPECT_VALUE_OR_END, self::EXPECT_KEY_OR_END], true)) {
                            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $char));
                        }

                        if (self::EXPECT_SEPARATOR === $expected) {
                            yield ($dict ? $key : null) => [$valueStart, $position - $valueStart];
                        }

                        $depth = 0;
                        $expected = self::EXPECT_END;

                        break;

                    case ',':
                        if (self::EXPECT_SEPARATOR !== $expected) {
                            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $char));
                        }

                        yield ($dict ? $key : null) => [$valueStart, $position - $valueStart];

                        $expected = $dict ? self::EXPECT_KEY : self::EXPECT_VALUE;

                        break;

                    case ':':
                        if (self::EXPECT_COLON !== $expected) {
                            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $char));
                        }

                        $expected = self::EXPECT_VALUE;

                        break;
                }
            }
        }

        if (null !== $rootToken) {
            yield $rootToken;

            $expected = self::EXPECT_END;
        }

        if ($depth > 0 || $inString || self::EXPECT_END !== $expected) {
            throw new InvalidStreamException('Unterminated JSON.');
        }
    }

    private static function decodeKey(string $rawKey): string
    {
        if (!preg_match('/[^\x20-\x5B\x5D-\x7E]/', $rawKey)) {
            return $rawKey;
        }

        if (!\is_string($key = json_decode('"'.$rawKey.'"'))) {
            throw new InvalidStreamException(\sprintf('Expected dict key, but got ""%s"".', $rawKey));
        }

        return $key;
    }

    private static function getToken(string $chunk, int $offset): string
    {
        return substr($chunk, $offset, max(1, strcspn($chunk, self::WHITESPACES.',:{}[]', $offset + 1) + 1));
    }

    /**
     * @param resource $stream
     *
     * @return \Generator<int, string>
     */
    private static function getChunks($stream, int $offset, ?int $length): \Generator
    {
        $infiniteLength = null === $length;
        $toReadLength = $length;

        if (stream_get_meta_data($stream)['seekable']) {
            rewind($stream);
        }

        while (($infiniteLength && !feof($stream)) || $toReadLength > 0) {
            if (false === $chunk = stream_get_contents($stream, $infiniteLength ? self::CHUNK_LENGTH : min(self::CHUNK_LENGTH, $toReadLength), $offset)) {
                throw new RuntimeException('Failed to read JSON stream.');
            }

            if ('' === $chunk) {
                break;
            }

            yield $offset => $chunk;

            $toReadLength -= \strlen($chunk);
            $offset += \strlen($chunk);
        }
    }
}
