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
    public static function get(array|\ArrayAccess $array, array $indices)
    {
        foreach ($indices as $index) {
            if (\is_array($array) ? !\array_key_exists($index, $array) : !isset($array[$index])) {
                throw new OutOfBoundsException(sprintf('The index "%s" does not exist.', $index));
            }

            $array = $array[$index];
        }

        return $array;
    }

    private function __construct()
    {
    }
}
