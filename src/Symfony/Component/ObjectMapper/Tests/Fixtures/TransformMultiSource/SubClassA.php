<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformMultiSource;

class SubClassA
{
    public function __construct(
        public string $foo = 'foo'
    ) {
    }
}
