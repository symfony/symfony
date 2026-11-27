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

#[Map(target: Car::class, if: [self::class, 'hasFourWheels'])]
#[Map(target: Truck::class, if: [self::class, 'hasMoreThanFourWheels'])]
#[Map(target: VehicleSummary::class)]
class VehicleSource
{
    public function __construct(
        public string $name,
        public int $wheels,
    ) {
    }

    public static function hasFourWheels(mixed $value, self $source): bool
    {
        return 4 === $source->wheels;
    }

    public static function hasMoreThanFourWheels(mixed $value, self $source): bool
    {
        return 4 < $source->wheels;
    }
}
