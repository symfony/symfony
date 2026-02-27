<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\EmbeddedMapping;

use Symfony\Component\ObjectMapper\Attribute\Map;

class UserDto
{
    public function __construct(
        #[Map(target: 'address.zipcode')]
        public string $userAddressZipcode,
        #[Map(target: 'address.city')]
        public string $userAddressCity,
        public string $name,
    ) {
    }
}
