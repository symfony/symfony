<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedConstructedTarget;

final class OuterMapped
{
    public function __construct(
        public InnerMapped $inner,
    ) {
    }
}
