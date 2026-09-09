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

/**
 * HMAC-SHA256, which needs nothing beyond what PHP always has.
 *
 * The default for that reason. An index writing one tag per row will not notice the difference
 * with {@see Blake2b}; one writing several, as a range index does, will.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class HmacSha256 implements AlgorithmInterface
{
    public function tag(#[\SensitiveParameter] string $value, #[\SensitiveParameter] string $key): string
    {
        return hash_hmac('sha256', $value, $key, true);
    }
}
