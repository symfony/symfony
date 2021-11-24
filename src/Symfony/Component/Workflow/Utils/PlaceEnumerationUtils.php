<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Utils;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class PlaceEnumerationUtils
{
    public static function getPlaceKey(string|\UnitEnum $place): string
    {
        if ($place instanceof \UnitEnum) {
            return \get_class($place).'::'.$place->name;
        }

        return $place;
    }

    public static function getTypedValue(string $place): string|\UnitEnum
    {
        try {
            $value = \constant($place);
            if ($value instanceof \UnitEnum) {
                // Assure we actually retrieved an enumeration case and not a constant with a name
                // that looks like an enumeration.
                return $value;
            }

            return $place;
        } catch (\Throwable) {
            return $place;
        }
    }
}
