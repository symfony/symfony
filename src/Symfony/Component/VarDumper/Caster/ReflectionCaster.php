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

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts Reflector related classes to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ReflectionCaster
{
    public static function castReflector(\Reflector $c, array $a, Stub $stub, $isNested)
    {
        $a["\0~\0reflection"] = $c->__toString();

        return $a;
    }

    public static function castClosure(\Closure $c, array $a, Stub $stub, $isNested)
    {
        $stub->class = 'Closure'; // HHVM generates unique class names for closures
        $a = static::castReflector(new \ReflectionFunction($c), $a, $stub, $isNested);
        unset($a["\0+\0000"], $a['name'], $a["\0+\0this"], $a["\0+\0parameter"]);

        return $a;
    }
}
