<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMapping;

class TargetUnion
{
    public function __construct(
        public NestedSourceA|NestedSourceB $item,
    ) {
    }
}
