<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ConditionalSourceMap;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

class UserDto
{
    public function __construct(
        #[Map(target: 'address.zipcode', if: new TargetClass(User::class))]
        #[Map(source: 'address.zipcode', if: new TargetClass(self::class))]
        public string $userAddressZipcode,
        #[Map(target: 'address.city', if: new TargetClass(User::class))]
        #[Map(source: 'address.city', if: new TargetClass(self::class))]
        public string $userAddressCity,
        public string $name,
    ) {
    }
}
