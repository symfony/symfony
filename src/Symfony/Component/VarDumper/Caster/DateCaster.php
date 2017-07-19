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
            ."\n".$fromNow->format('%R').self::formatInterval($fromNow).' from now'
            .($location ? ($d->format('I') ? "\nDST On" : "\nDST Off") : '')
        ;

        $a = array();
        $a[$prefix.'date'] = new ConstStub($d->format('Y-m-d H:i:s.u '.($location ? 'e (P)' : 'P')), $title);

        $stub->class .= $d->format(' @U');

        return $a;
    }

    public static function castInterval(\DateInterval $interval, array $a, Stub $stub, $isNested, $filter)
    {
        $now = new \DateTimeImmutable();
        $numberOfSeconds = $now->add($interval)->getTimestamp() - $now->getTimestamp();
        $title = number_format($numberOfSeconds, 0, '.', ' ').'s';

        $i = array(Caster::PREFIX_VIRTUAL.'interval' => new ConstStub(self::formatInterval($interval), $title));

        return $filter & Caster::EXCLUDE_VERBOSE ? $i : $i + $a;
    }

    private static function formatInterval(\DateInterval $i)
    {
        $format = '%R '
            .($i->y ? '%yy ' : '')
            .($i->m ? '%mm ' : '')
            .($i->d ? '%dd ' : '')
        ;

        if (\PHP_VERSION_ID >= 70100 && isset($i->f)) {
            $format .= $i->h || $i->i || $i->s || $i->f ? '%H:%I:%S.%F' : '';
        } else {
            $format .= $i->h || $i->i || $i->s ? '%H:%I:%S' : '';
        }

        $format = '%R ' === $format ? '0s' : $format;

        return $i->format(rtrim($format));
    }

    public static function castTimeZone(\DateTimeZone $timeZone, array $a, Stub $stub, $isNested, $filter)
    {
        $location = $timeZone->getLocation();
        $formatted = (new \Datetime('now', $timeZone))->format($location ? 'e (P)' : 'P');
        $title = $location && extension_loaded('intl') ? \Locale::getDisplayRegion('-'.$location['country_code'], \Locale::getDefault()) : '';

        $z = array(Caster::PREFIX_VIRTUAL.'timezone' => new ConstStub($formatted, $title));

        return $filter & Caster::EXCLUDE_VERBOSE ? $z : $z + $a;
    }
}
