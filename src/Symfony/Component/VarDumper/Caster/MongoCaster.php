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

@trigger_error('The '.__NAMESPACE__.'\MongoCaster class is deprecated since Symfony 3.4 and will be removed in 4.0.', E_USER_DEPRECATED);

/**
 * Casts classes from the MongoDb extension to array representation.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since version 3.4, to be removed in 4.0.
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
