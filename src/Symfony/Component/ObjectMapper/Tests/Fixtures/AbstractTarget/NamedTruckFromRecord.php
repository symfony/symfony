<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\AbstractTarget;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: TruckRecord::class, if: [self::class, 'isNamed'])]
class NamedTruckFromRecord extends Vehicle
{
    public static function isNamed(mixed $value, VehicleRecord $source): bool
    {
        return '' !== $source->name;
    }
}
