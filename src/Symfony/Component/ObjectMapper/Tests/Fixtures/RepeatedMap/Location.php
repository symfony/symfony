<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\RepeatedMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: Profile::class)]
class Location
{
    public function __construct(
        #[Map(target: 'address')]
        public Street $street = new Street(),
        #[Map(target: 'address')]
        public City $city = new City(),
    ) {
    }
}
