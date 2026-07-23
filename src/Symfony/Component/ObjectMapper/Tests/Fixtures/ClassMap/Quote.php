<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

final class Quote
{
    public function __construct(
        public string $id,
        public Cost $cost,
    ) {
    }
}
