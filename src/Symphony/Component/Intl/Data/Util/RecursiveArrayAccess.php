<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\Data\Util;

use Symphony\Component\Intl\Exception\OutOfBoundsException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class RecursiveArrayAccess
{
    public static function get($array, array $indices)
    {
        foreach ($indices as $index) {
            // Use array_key_exists() for arrays, isset() otherwise
            if (is_array($array)) {
                if (array_key_exists($index, $array)) {
                    $array = $array[$index];
                    continue;
                }
            } elseif ($array instanceof \ArrayAccess) {
                if (isset($array[$index])) {
                    $array = $array[$index];
                    continue;
                }
            }

            throw new OutOfBoundsException(sprintf(
                'The index %s does not exist.',
                $index
            ));
        }

        return $array;
    }

    private function __construct()
    {
    }
}
