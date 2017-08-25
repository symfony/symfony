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
 * Casts a caster's Stub.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class StubCaster
{
    public static function castStub(Stub $c, array $a, Stub $stub, $isNested)
    {
        if ($isNested) {
            $stub->type = $c->type;
            $stub->class = $c->class;
            $stub->value = $c->value;
            $stub->handle = $c->handle;
            $stub->cut = $c->cut;

            $a = array();
        }

        return $a;
    }

    public static function castCutArray(CutArrayStub $c, array $a, Stub $stub, $isNested)
    {
        return $isNested ? $c->preservedSubset : $a;
    }

    public static function cutInternals($obj, array $a, Stub $stub, $isNested)
    {
        if ($isNested) {
            $stub->cut += count($a);

            return array();
        }

        return $a;
    }

    public static function castEnum(EnumStub $c, array $a, Stub $stub, $isNested)
    {
        if ($isNested) {
            $stub->class = '';
            $stub->handle = 0;
            $stub->value = null;

            $a = array();

            if ($c->value) {
                foreach (array_keys($c->value) as $k) {
                    $keys[] = !isset($k[0]) || "\0" !== $k[0] ? Caster::PREFIX_VIRTUAL.$k : $k;
                }
                // Preserve references with array_combine()
                $a = array_combine($keys, $c->value);
            }
        }

        return $a;
    }
}
