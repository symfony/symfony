<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedConstructedTarget;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: InnerMapped::class)]
final class Inner
{
    public function __construct(
        public string $name,
    ) {
    }
}
