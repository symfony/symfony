<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty;

use Symfony\Component\ObjectMapper\Attribute\Map;

final class ReadOnlyPromotedPropertyBMapped
{
    public function __construct(
        public string $var2,
    ) {
    }
}
