<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: ReadOnlyPromotedPropertyBMapped::class)]
final class ReadOnlyPromotedPropertyB
{
    public function __construct(
        public string $var2,
    ) {
    }
}
