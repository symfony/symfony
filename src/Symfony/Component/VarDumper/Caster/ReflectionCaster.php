<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * Casts Reflector related classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ReflectionCaster
{
    public static function castReflector(\Reflector $c, array $a)
    {
        $a["\0~\0reflection"] = $c->__toString();

        return $a;
    }

    public static function castClosure(\Closure $c, array $a)
    {
        $a = static::castReflector(new \ReflectionFunction($c), $a);
        unset($a["\0+\0000"], $a['name']);

        return $a;
    }
}
