<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Exception\InvalidArgumentException;

/**
 * Hex and base64 conversion helpers.
 *
 * When the sodium extension is loaded the timing-safe `sodium_*` variants are
 * used. Decode methods throw InvalidArgumentException on malformed input.
 *
 * @author David Gebler <me@davegebler.com>
 */
final class Encoding
{
    public static function toHex(string $bytes): string
    {
        if (\function_exists('sodium_bin2hex')) {
            return sodium_bin2hex($bytes);
        }

        return bin2hex($bytes);
    }

    public static function fromHex(string $hex): string
    {
        if (\function_exists('sodium_hex2bin')) {
            try {
                return sodium_hex2bin($hex);
            } catch (\SodiumException $e) {
                throw new InvalidArgumentException('Input is not valid hex.', 0, $e);
            }
        }

        if ('' !== $hex && 1 !== preg_match('/^(?:[0-9a-fA-F]{2})+$/', $hex)) {
            throw new InvalidArgumentException('Input is not valid hex.');
        }

        $decoded = hex2bin($hex);
        if (false === $decoded) {
            throw new InvalidArgumentException('Input is not valid hex.');
        }

        return $decoded;
    }

    public static function toBase64(string $bytes): string
    {
        if (\function_exists('sodium_bin2base64')) {
            return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_ORIGINAL);
        }

        return base64_encode($bytes);
    }

    public static function fromBase64(string $base64): string
    {
        if (\function_exists('sodium_base642bin')) {
            try {
                return sodium_base642bin($base64, \SODIUM_BASE64_VARIANT_ORIGINAL);
            } catch (\SodiumException $e) {
                throw new InvalidArgumentException('Input is not valid base64.', 0, $e);
            }
        }

        $decoded = base64_decode($base64, true);
        if (false === $decoded) {
            throw new InvalidArgumentException('Input is not valid base64.');
        }

        return $decoded;
    }
}
