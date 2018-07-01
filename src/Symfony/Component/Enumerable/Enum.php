<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Enumerable;

abstract class Enum
{
    private static $cache = array();

    final public static function getConstants(): array
    {
        $calledClass = get_called_class();
        if (!array_key_exists($calledClass, self::$cache)) {
            $reflect = new \ReflectionClass($calledClass);
            self::$cache[$calledClass] = $reflect->getConstants();
        }

        return self::$cache[$calledClass];
    }

    final public static function isValidName(string $name): bool
    {
        $constants = self::getConstants();

        return array_key_exists($name, $constants);
    }

    final public static function isValidValue($value): bool
    {
        $values = array_values(self::getConstants());

        return in_array($value, $values, true);
    }
}
