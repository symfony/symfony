<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformMultiSource;

class SubClassB
{
    public function __construct(
        public string $bar = 'bar'
    ) {
    }
}
