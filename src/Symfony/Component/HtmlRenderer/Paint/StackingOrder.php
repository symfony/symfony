<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Paint;

/**
 * @internal
 */
final class StackingOrder
{
    public static function compare(array $a, array $b): int
    {
        $count = min(\count($a['levels']), \count($b['levels']));
        for ($i = 0; $i < $count; ++$i) {
            [$zA, $depthA] = $a['levels'][$i];
            [$zB, $depthB] = $b['levels'][$i];
            if (0 !== $order = $zA <=> $zB) {
                return $order;
            }
            // In document order: the paths up to those depths, an ancestor before what it contains
            for ($j = 0, $common = min($depthA, $depthB); $j < $common; ++$j) {
                if (0 !== $order = $a['path'][$j] <=> $b['path'][$j]) {
                    return $order;
                }
            }
            if (0 !== $order = $depthA <=> $depthB) {
                return $order;
            }
        }

        return \count($a['levels']) <=> \count($b['levels']);
    }
}
