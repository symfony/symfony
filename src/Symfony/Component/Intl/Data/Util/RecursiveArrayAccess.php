<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Util;

use Symfony\Component\Intl\Exception\OutOfBoundsException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class RecursiveArrayAccess
{
    public static function get(mixed $array, array $indices): mixed
    {
        foreach ($indices as $index) {
            // Use array_key_exists() for arrays, isset() otherwise
            if (\is_array($array)) {
                if (\array_key_exists($index, $array)) {
                    $array = $array[$index];
                    continue;
                }
            } elseif ($array instanceof \ArrayAccess) {
                if (isset($array[$index])) {
                    $array = $array[$index];
                    continue;
                }
            }

            throw new OutOfBoundsException(sprintf('The index "%s" does not exist.', $index));
        }

        return $array;
    }

    private function __construct()
    {
    }
}
