<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\BlindIndex;

use Symfony\Component\KeyManagement\Exception\LogicException;

/**
 * Keyed BLAKE2b, around three times faster than {@see HmacSha256} for the same 32 bytes of tag.
 *
 * Worth the ext-sodium requirement when a row carries several tags, which is what indexing
 * anything beyond a single equality costs.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class Blake2b implements AlgorithmInterface
{
    public function __construct()
    {
        if (!\function_exists('sodium_crypto_generichash')) {
            throw new LogicException('The "sodium" PHP extension is required to derive blind index tags with BLAKE2b.');
        }
    }

    public function tag(#[\SensitiveParameter] string $value, #[\SensitiveParameter] string $key): string
    {
        return sodium_crypto_generichash($value, $key, 32);
    }
}
