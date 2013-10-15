<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Util;

use Symfony\Component\Intl\Exception\OutOfBoundsException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursiveArrayAccess
{
    public static function get($array, array $indices)
    {
        foreach ($indices as $index) {
            // Use array_key_exists() for arrays, isset() otherwise
            if (is_array($array) && !array_key_exists($index, $array) || !is_array($array) && !isset($array[$index])) {
                throw new OutOfBoundsException('The index '.$index.' does not exist.');
            }

            if ($array instanceof \ArrayAccess) {
                $array = $array->offsetGet($index);
            } else {
                $array = $array[$index];
            }
        }

        return $array;
    }

    private function __construct() {}
}
