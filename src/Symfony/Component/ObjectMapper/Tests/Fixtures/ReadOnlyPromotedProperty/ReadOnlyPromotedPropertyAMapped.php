<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty;

final class ReadOnlyPromotedPropertyAMapped
{
    public function __construct(
        public ReadOnlyPromotedPropertyBMapped $b,
        public string $var1,
    ) {
    }
}
