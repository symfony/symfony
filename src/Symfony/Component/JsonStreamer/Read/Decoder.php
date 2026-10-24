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
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;

/**
 * Decodes string or stream using the native "json_decode" PHP function.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class Decoder
{
    public static function decodeString(string $json): mixed
    {
        try {
            return json_decode($json, associative: true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UnexpectedValueException('JSON is not valid: '.$e->getMessage());
        }
    }

    /**
     * @param resource $stream
     */
    public static function decodeStream($stream, int $offset = 0, ?int $length = null, bool $associative = true): mixed
    {
        if (false === $contents = stream_get_contents($stream, $length ?? -1, $offset)) {
            throw new RuntimeException('Failed to read JSON stream.');
        }

        try {
            return json_decode($contents, associative: $associative, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidStreamException('JSON is not valid: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param resource $stream
     */
    public static function validateStream($stream, int $offset, int $length): void
    {
        if (false === $contents = stream_get_contents($stream, $length, $offset)) {
            throw new RuntimeException('Failed to read JSON stream.');
        }

        if (!json_validate($contents)) {
            throw new InvalidStreamException('JSON is not valid: '.json_last_error_msg());
        }
    }

    /**
     * Tells whether the JSON stream part holds an object key twice, as "json_decode" silently keeps the last value.
     *
     * Objects must have been decoded as "\stdClass" in $data, associative arrays lead to false positives only.
     *
     * @param resource $stream
     */
    public static function hasDuplicateKeys($stream, int $offset, int $length, mixed $data): bool
    {
        if (false === $contents = stream_get_contents($stream, $length, $offset)) {
            throw new RuntimeException('Failed to read JSON stream.');
        }

        // as the JSON is valid, every string followed by a colon is a key, and strings that are not are skipped whole
        // on failure, preg_match_all() returns false, so duplicated keys are assumed
        return preg_match_all('/"(?:[^"\\\\]++|\\\\.)*+"(?:\s*+:|(*SKIP)(*FAIL))/', $contents) !== self::countKeys($data);
    }

    private static function countKeys(mixed $data): int
    {
        if ($data instanceof \stdClass) {
            $data = (array) $data;
            $count = \count($data);
        } elseif (\is_array($data)) {
            $count = 0;
        } else {
            return 0;
        }

        foreach ($data as $value) {
            if (\is_array($value) || $value instanceof \stdClass) {
                $count += self::countKeys($value);
            }
        }

        return $count;
    }
}
