<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty;

use Symfony\Component\ObjectMapper\Attribute\Map;

final class ReadOnlyPromotedPropertyAMapped
{
    public function __construct(
        public ReadOnlyPromotedPropertyBMapped $b,
        public string $var1,
    ) {
    }
}
