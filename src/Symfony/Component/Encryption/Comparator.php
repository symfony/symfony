<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption;

/**
 * Constant-time string comparison for secrets, tokens, MACs, and hashes.
 *
 * The comparison time depends only on the length of the first argument, never
 * on its content, so it does not leak how many leading bytes matched.
 *
 * @author David Gebler <me@davegebler.com>
 */
final class Comparator
{
    public static function equals(string $known, string $userSupplied): bool
    {
        return hash_equals($known, $userSupplied);
    }
}
