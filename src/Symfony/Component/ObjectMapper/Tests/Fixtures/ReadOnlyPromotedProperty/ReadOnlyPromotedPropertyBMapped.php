<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ReadOnlyPromotedProperty;

final class ReadOnlyPromotedPropertyBMapped
{
    public function __construct(
        public string $var2,
    ) {
    }
}
