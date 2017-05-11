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

    public static function cutInternals($obj, array $a, Stub $stub, $isNested)
    {
        if ($isNested) {
            $stub->cut += count($a);

            return array();
        }

        return $a;
    }
}
