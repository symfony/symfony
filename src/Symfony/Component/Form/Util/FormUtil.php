<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

abstract class FormUtil
{
    public static function toArrayKey($value)
    {
        if ((string)(int)$value === (string)$value) {
            return (int)$value;
        }

        if (is_bool($value)) {
            return (int)$value;
        }

        return (string)$value;
    }

    public static function toArrayKeys(array $array)
    {
        return array_map(array(__CLASS__, 'toArrayKey'), $array);
    }
}