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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RecursiveArrayAccess
{
    public static function get($array, array $indices)
    {
        foreach ($indices as $index) {
            if (!$array instanceof \ArrayAccess && !is_array($array)) {
                return null;
            }

            $array = $array[$index];
        }

        return $array;
    }

    private function __construct() {}
}
