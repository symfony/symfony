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
 * Casts DateTimeInterface related classes to array representation.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class DateCaster
{
    public static function castDateTime(\DateTimeInterface $d, array $a, Stub $stub, $isNested, $filter)
    {
        $prefix = Caster::PREFIX_VIRTUAL;
        $location = $d->getTimezone()->getLocation();
        $fromNow = (new \DateTime())->diff($d);

        $title = $d->format('l, F j, Y')
            ."\n".$fromNow->format('%R').(ltrim($fromNow->format('%yy %mm %dd %H:%I:%Ss'), ' 0ymd:s') ?: '0s').' from now'
            .($location ? ($d->format('I') ? "\nDST On" : "\nDST Off") : '')
        ;

        $a = array();
        $a[$prefix.'date'] = new ConstStub($d->format('Y-m-d H:i:s.u '.($location ? 'e (P)' : 'P')), $title);

        $stub->class .= $d->format(' @U');

        return $a;
    }
}
