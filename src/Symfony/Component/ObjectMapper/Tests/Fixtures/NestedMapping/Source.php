<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMapping;

class Source
{
    public function __construct(
        public NestedSourceA $item,
    ) {
    }
}
