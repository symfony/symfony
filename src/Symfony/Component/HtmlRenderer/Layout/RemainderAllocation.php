<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

/** @internal */
final class RemainderAllocation
{
    public static function round(array $shares, array $priorities = []): array
    {
        $allocated = $remainders = [];
        foreach ($shares as $index => $share) {
            $allocated[$index] = (int) floor($share);
            $remainders[$index] = $share - $allocated[$index];
        }
        $left = (int) round(array_sum($shares)) - array_sum($allocated);
        uksort($remainders, static fn (int $first, int $second): int => [$remainders[$second], $priorities[$first] ?? 0, $first] <=> [$remainders[$first], $priorities[$second] ?? 0, $second]);
        foreach (\array_slice(array_keys($remainders), 0, max(0, $left)) as $index) {
            ++$allocated[$index];
        }

        return $allocated;
    }
}
