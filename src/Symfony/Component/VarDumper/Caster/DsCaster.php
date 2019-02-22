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

use Ds\Deque;
use Ds\Map;
use Ds\PriorityQueue;
use Ds\Queue;
use Ds\Set;
use Ds\Stack;
use Ds\Vector;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts Ds extension classes to array representation.
 *
 * @author Jáchym Toušek <enumag@gmail.com>
 */
class DsCaster
{
    /**
     * @param Set|Deque|Vector|Stack|Queue|PriorityQueue $c
     */
    public static function castDs($c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;
        $a = $c->toArray();
        $a[$prefix.'capacity'] = $c->capacity();

        return $a;
    }

    public static function castMap(Map $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;
        $a = $c->pairs()->toArray();
        $a[$prefix.'capacity'] = $c->capacity();

        return $a;
    }
}
