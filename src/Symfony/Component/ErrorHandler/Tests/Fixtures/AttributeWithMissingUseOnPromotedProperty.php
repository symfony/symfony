<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

class AttributeWithMissingUseOnPromotedProperty
{
    public function __construct(
        #[MissingPromotedPropertyAttribute]
        public int $promoted = 0,
    ) {
    }
}
