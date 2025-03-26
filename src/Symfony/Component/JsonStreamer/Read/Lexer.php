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
 * Retrieves lexical tokens from a given stream.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class Lexer
{
    private const MAX_CHUNK_LENGTH = 8192;

    private const WHITESPACE_CHARS = [' ' => true, "\r" => true, "\t" => true, "\n" => true];
    private const STRUCTURE_CHARS = [',' => true, ':' => true, '{' => true, '}' => true, '[' => true, ']' => true];

    private const TOKEN_DICT_START = 1;
    private const TOKEN_DICT_END = 2;
    private const TOKEN_LIST_START = 4;
    private const TOKEN_LIST_END = 8;
    private const TOKEN_KEY = 16;
    private const TOKEN_COLUMN = 32;
    private const TOKEN_COMMA = 64;
    private const TOKEN_SCALAR = 128;
    private const TOKEN_END = 256;
    private const TOKEN_VALUE = self::TOKEN_DICT_START | self::TOKEN_LIST_START | self::TOKEN_SCALAR;

    private const KEY_REGEX = '/^(?:(?>"(?>\\\\(?>["\\\\\/bfnrt]|u[a-fA-F0-9]{4})|[^\0-\x1F\\\\"]+)*"))$/u';
    private const SCALAR_REGEX = '/^(?:(?:(?>"(?>\\\\(?>["\\\\\/bfnrt]|u[a-fA-F0-9]{4})|[^\0-\x1F\\\\"]+)*"))|(?:(?>-?(?>0|[1-9][0-9]*)(?>\.[0-9]+)?(?>[eE][+-]?[0-9]+)?))|true|false|null)$/u';

    /**
     * @param resource $stream
     *
     * @return \Iterator<array{0: string, 1: int}>
     *
     * @throws InvalidStreamException
     */
    public function getTokens($stream, int $offset, ?int $length): \Iterator
    {
        /**
         * @var array{expected_token: int-mask-of<self::TOKEN_*>, pointer: int, structures: array<int, 'list'|'dict'>, keys: list<array<string, true>>} $context
         */
        $context = [
            'expected_token' => self::TOKEN_VALUE,
            'pointer' => -1,
            'structures' => [],
            'keys' => [],
        ];

        $currentTokenPosition = $offset;
        $token = '';
        $inString = $escaping = false;

        foreach ($this->getChunks($stream, $offset, $length) as $chunk) {
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

        if (!(self::TOKEN_END & $context['expected_token'])) {
            throw new InvalidStreamException('Unterminated JSON.');
        }
    }

    /**
     * @param resource $stream
     *
     * @return \Iterator<string>
     */
    private function getChunks($stream, int $offset, ?int $length): \Iterator
    {
        $infiniteLength = null === $length;
        $chunkLength = $infiniteLength ? self::MAX_CHUNK_LENGTH : min($length, self::MAX_CHUNK_LENGTH);
        $toReadLength = $length;

        rewind($stream);

        while (!feof($stream) && ($infiniteLength || $toReadLength > 0)) {
            if (false === $chunk = stream_get_contents($stream, $infiniteLength ? $chunkLength : min($chunkLength, $toReadLength), $offset)) {
                throw new RuntimeException('Failed to read JSON stream.');
            }

            $toReadLength -= $l = \strlen($chunk);
            $offset += $l;

            yield $chunk;
        }
    }

    /**
     * @param array{expected_token: int-mask-of<self::TOKEN_*>, pointer: int, structures: list<'list'|'dict'>, keys: list<array<string, true>>} $context
     *
     * @throws InvalidStreamException
     */
    private function validateToken(string $token, array &$context): void
    {
        if ('{' === $token) {
            if (!(self::TOKEN_DICT_START & $context['expected_token'])) {
                throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $token));
            }

            ++$context['pointer'];
            $context['structures'][$context['pointer']] = 'dict';
            $context['keys'][$context['pointer']] = [];
            $context['expected_token'] = self::TOKEN_DICT_END | self::TOKEN_KEY;

            return;
        }

        if ('}' === $token) {
            if (!(self::TOKEN_DICT_END & $context['expected_token']) || -1 === $context['pointer']) {
                throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $token));
            }

            unset($context['keys'][$context['pointer']]);
            --$context['pointer'];

            if (-1 === $context['pointer']) {
                $context['expected_token'] = self::TOKEN_END;
            } else {
                $context['expected_token'] = 'list' === $context['structures'][$context['pointer']] ? self::TOKEN_LIST_END | self::TOKEN_COMMA : self::TOKEN_DICT_END | self::TOKEN_COMMA;
            }

            return;
        }

        if ('[' === $token) {
            if (!(self::TOKEN_LIST_START & $context['expected_token'])) {
                throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $token));
            }

            $context['expected_token'] = self::TOKEN_LIST_END | self::TOKEN_VALUE;
            $context['structures'][++$context['pointer']] = 'list';

            return;
        }

        if (']' === $token) {
            if (!(self::TOKEN_LIST_END & $context['expected_token']) || -1 === $context['pointer']) {
                throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $token));
            }

            --$context['pointer'];

            if (-1 === $context['pointer']) {
                $context['expected_token'] = self::TOKEN_END;
            } else {
                $context['expected_token'] = 'list' === $context['structures'][$context['pointer']] ? self::TOKEN_LIST_END | self::TOKEN_COMMA : self::TOKEN_DICT_END | self::TOKEN_COMMA;
            }

            return;
        }

        if (',' === $token) {
            if (!(self::TOKEN_COMMA & $context['expected_token']) || -1 === $context['pointer']) {
                throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $token));
            }

            $context['expected_token'] = 'dict' === $context['structures'][$context['pointer']] ? self::TOKEN_KEY : self::TOKEN_VALUE;

            return;
        }

        if (':' === $token) {
            if (!(self::TOKEN_COLUMN & $context['expected_token']) || 'dict' !== ($context['structures'][$context['pointer']] ?? null)) {
                throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $token));
            }

            $context['expected_token'] = self::TOKEN_VALUE;

            return;
        }

        if (self::TOKEN_VALUE & $context['expected_token'] && !preg_match(self::SCALAR_REGEX, $token)) {
            throw new InvalidStreamException(\sprintf('Expected scalar value, but got "%s".', $token));
        }

        if (-1 === $context['pointer']) {
            if (self::TOKEN_VALUE & $context['expected_token']) {
                $context['expected_token'] = self::TOKEN_END;

                return;
            }

            throw new InvalidStreamException(\sprintf('Expected end, but got "%s".', $token));
        }

        if ('dict' === $context['structures'][$context['pointer']]) {
            if (self::TOKEN_KEY & $context['expected_token']) {
                if (!preg_match(self::KEY_REGEX, $token)) {
                    throw new InvalidStreamException(\sprintf('Expected dict key, but got "%s".', $token));
                }

                if (isset($context['keys'][$context['pointer']][$token])) {
                    throw new InvalidStreamException(\sprintf('Got %s dict key twice.', $token));
                }

                $context['keys'][$context['pointer']][$token] = true;
                $context['expected_token'] = self::TOKEN_COLUMN;

                return;
            }

            if (self::TOKEN_VALUE & $context['expected_token']) {
                $context['expected_token'] = self::TOKEN_DICT_END | self::TOKEN_COMMA;

                return;
            }

            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $token));
        }

        if ('list' === $context['structures'][$context['pointer']]) {
            if (self::TOKEN_VALUE & $context['expected_token']) {
                $context['expected_token'] = self::TOKEN_LIST_END | self::TOKEN_COMMA;

                return;
            }

            throw new InvalidStreamException(\sprintf('Unexpected "%s" token.', $token));
        }
    }
}
