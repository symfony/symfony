<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

final class Cost
{
    public function __construct(
        public int $amount,
        public int $tax,
        public ?string $bar = null,
    ) {
    }
}
