<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\InheritedClassMap;

class InheritedReverseSource
{
    public function __construct(
        public string $id = 'foo',
    ) {
    }
}
