<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMapping;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: NestedSourceB::class)]
class NestedSourceA
{
    public function __construct(
        public string $foo,
        public string $bar,
    ) {
    }
}
