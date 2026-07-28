<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedConstructedTarget;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: OuterMapped::class)]
final class Outer
{
    public function __construct(
        public Inner $inner,
    ) {
    }
}
