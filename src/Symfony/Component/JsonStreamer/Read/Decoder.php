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
    public static function decodeStream($stream, int $offset = 0, ?int $length = null): mixed
    {
        return self::decodeString(stream_get_contents($stream, $length ?? -1, $offset));
    }
}
