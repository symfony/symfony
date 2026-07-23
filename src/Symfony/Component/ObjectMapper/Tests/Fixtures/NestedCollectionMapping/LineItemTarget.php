<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedCollectionMapping;

class LineItemTarget
{
    public function __construct(
        public string $productName = '',
        public int $quantity = 0,
        public float $amount = 0.0,
    ) {
    }
}
