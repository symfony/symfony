<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Decode;

use Symfony\Component\JsonEncoder\Exception\InvalidStreamException;
use Symfony\Component\JsonEncoder\Stream\StreamReaderInterface;

/**
 * Retrieves lexical tokens from a given stream.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final readonly class Lexer
{
    private const MAX_CHUNK_LENGTH = 8192;

    private const WHITESPACE_CHARS = [' ' => true, "\r" => true, "\t" => true, "\n" => true];
    private const STRUCTURE_CHARS = [',' => true, ':' => true, '{' => true, '}' => true, '[' => true, ']' => true];

    private const DICT_START = 1;
    private const DICT_END = 2;
    private const LIST_START = 4;
    private const LIST_END = 8;
    private const KEY = 16;
    private const COLUMN = 32;
    private const COMMA = 64;
    private const SCALAR = 128;
    private const END = 256;
    private const VALUE = self::DICT_START | self::LIST_START | self::SCALAR;

    private const KEY_REGEX = '/^(?:(?>"(?>\\\\(?>["\\\\\/bfnrt]|u[a-fA-F0-9]{4})|[^\0-\x1F\\\\"]+)*"))$/u';
    private const SCALAR_REGEX = '/^(?:(?:(?>"(?>\\\\(?>["\\\\\/bfnrt]|u[a-fA-F0-9]{4})|[^\0-\x1F\\\\"]+)*"))|(?:(?>-?(?>0|[1-9][0-9]*)(?>\.[0-9]+)?(?>[eE][+-]?[0-9]+)?))|true|false|null)$/u';

    /**
     * @param StreamReaderInterface|resource $stream
     *
     * @return \Iterator<array{0: string, 1: int}>
     *
     * @throws InvalidStreamException
     */
    public function getTokens(mixed $stream, int $offset, ?int $length): \Iterator
    {
        $context = [
            'expected' => self::VALUE,
            'pointer' => -1,
            'structures' => [],
            'keys' => [],
        ];

        $currentTokenPosition = $offset;
        $token = '';
        $inString = $escaping = false;

        foreach ($this->getChunks($stream, $offset, $length) as $chunk) {
            $chunkLength = \strlen($chunk);

            foreach (str_split($chunk) as $byte) {
                if ($escaping) {
                    $escaping = false;
                    $token .= $byte;

                    continue;
                }

                if ($inString) {
                    $token .= $byte;

                    if ('"' === $byte) {
                        $inString = false;
                    } elseif ('\\' === $byte) {
                        $escaping = true;
                    }

                    continue;
                }

                if ('"' === $byte) {
                    $token .= $byte;
                    $inString = true;

                    continue;
                }

                if (isset(self::STRUCTURE_CHARS[$byte]) || isset(self::WHITESPACE_CHARS[$byte])) {
                    if ('' !== $token) {
                        $this->validateToken($token, $context);
                        yield [$token, $currentTokenPosition];

                        $currentTokenPosition += \strlen($token);
                        $token = '';
                    }

                    if (!isset(self::WHITESPACE_CHARS[$byte])) {
                        $this->validateToken($byte, $context);
                        yield [$byte, $currentTokenPosition];
                    }

                    if ('' !== $byte) {
                        ++$currentTokenPosition;
                    }

                    continue;
                }

                $token .= $byte;
            }
        }

        if ('' !== $token) {
            $this->validateToken($token, $context);
            yield [$token, $currentTokenPosition];
        }

        if (!(self::END & $context['expected'])) {
            throw new InvalidStreamException();
        }
    }

    /**
     * @param StreamReaderInterface|resource $stream
     *
     * @return \Iterator<string>
     */
    private function getChunks(mixed $stream, int $offset, ?int $length): \Iterator
    {
        $infiniteLength = null === $length;
        $chunkLength = $infiniteLength ? self::MAX_CHUNK_LENGTH : min($length, self::MAX_CHUNK_LENGTH);
        $toReadLength = $length;

        if (\is_resource($stream)) {
            rewind($stream);

            while (!feof($stream) && ($infiniteLength || $toReadLength > 0)) {
                $chunk = stream_get_contents($stream, $infiniteLength ? $chunkLength : min($chunkLength, $toReadLength), $offset);
                $toReadLength -= $l = \strlen($chunk);
                $offset += $l;

                yield $chunk;
            }

            return;
        }

        $stream->seek($offset);

        foreach ($stream as $chunk) {
            if (!$infiniteLength && $toReadLength <= 0) {
                break;
            }

            $chunkLength = \strlen($chunk);

            if (!$infiniteLength && $chunkLength > $toReadLength) {
                yield substr($chunk, 0, $toReadLength);

                break;
            }

            $toReadLength -= $chunkLength;

            yield $chunk;
        }
    }

    /**
     * @param array{expected: int, pointer: int, structures: list<string>, keys: list<array<string, true>>} $context
     *
     * @throws InvalidStreamException
     */
    private function validateToken(string $token, array &$context): void
    {
        if ('{' === $token) {
            if (!(self::DICT_START & $context['expected'])) {
                throw new InvalidStreamException();
            }

            ++$context['pointer'];
            $context['structures'][$context['pointer']] = 'dict';
            $context['keys'][$context['pointer']] = [];
            $context['expected'] = self::DICT_END | self::KEY;

            return;
        }

        if ('}' === $token) {
            if (!(self::DICT_END & $context['expected']) || -1 === $context['pointer']) {
                throw new InvalidStreamException();
            }

            unset($context['keys'][$context['pointer']]);
            --$context['pointer'];

            if (-1 === $context['pointer']) {
                $context['expected'] = self::END;
            } else {
                $context['expected'] = 'list' === $context['structures'][$context['pointer']] ? self::LIST_END | self::COMMA : self::DICT_END | self::COMMA;
            }

            return;
        }

        if ('[' === $token) {
            if (!(self::LIST_START & $context['expected'])) {
                throw new InvalidStreamException();
            }

            $context['expected'] = self::LIST_END | self::VALUE;
            $context['structures'][++$context['pointer']] = 'list';

            return;
        }

        if (']' === $token) {
            if (!(self::LIST_END & $context['expected']) || -1 === $context['pointer']) {
                throw new InvalidStreamException();
            }

            --$context['pointer'];

            if (-1 === $context['pointer']) {
                $context['expected'] = self::END;
            } else {
                $context['expected'] = 'list' === $context['structures'][$context['pointer']] ? self::LIST_END | self::COMMA : self::DICT_END | self::COMMA;
            }

            return;
        }

        if (',' === $token) {
            if (!(self::COMMA & $context['expected']) || -1 === $context['pointer']) {
                throw new InvalidStreamException();
            }

            $context['expected'] = 'dict' === $context['structures'][$context['pointer']] ? self::KEY : self::VALUE;

            return;
        }

        if (':' === $token) {
            if (!(self::COLUMN & $context['expected']) || 'dict' !== ($context['structures'][$context['pointer']] ?? null)) {
                throw new InvalidStreamException();
            }

            $context['expected'] = self::VALUE;

            return;
        }

        if (self::VALUE & $context['expected'] && !preg_match(self::SCALAR_REGEX, $token)) {
            throw new InvalidStreamException();
        }

        if (-1 === $context['pointer']) {
            if (self::VALUE & $context['expected']) {
                $context['expected'] = self::END;

                return;
            }

            throw new InvalidStreamException();
        }

        if ('dict' === $context['structures'][$context['pointer']]) {
            if (self::KEY & $context['expected']) {
                if (!preg_match(self::KEY_REGEX, $token)) {
                    throw new InvalidStreamException();
                }

                if (isset($context['keys'][$context['pointer']][$token])) {
                    throw new InvalidStreamException();
                }

                $context['keys'][$context['pointer']][$token] = true;
                $context['expected'] = self::COLUMN;

                return;
            }

            if (self::VALUE & $context['expected']) {
                $context['expected'] = self::DICT_END | self::COMMA;

                return;
            }

            throw new InvalidStreamException();
        }

        if ('list' === $context['structures'][$context['pointer']]) {
            if (self::VALUE & $context['expected']) {
                $context['expected'] = self::LIST_END | self::COMMA;

                return;
            }

            throw new InvalidStreamException();
        }
    }
}
