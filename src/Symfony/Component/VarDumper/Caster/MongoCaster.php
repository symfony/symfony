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
 * Casts classes from the MongoDb extension to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class MongoCaster
{
    public static function castCursor(\MongoCursorInterface $cursor, array $a, Stub $stub, $isNested)
    {
        if ($info = $cursor->info()) {
            foreach ($info as $k => $v) {
                $a[Caster::PREFIX_VIRTUAL.$k] = $v;
            }
        }
        $a[Caster::PREFIX_VIRTUAL.'dead'] = $cursor->dead();

        return $a;
    }
}
