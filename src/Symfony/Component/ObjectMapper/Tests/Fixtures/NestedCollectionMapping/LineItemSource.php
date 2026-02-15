<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedCollectionMapping;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: LineItemTarget::class)]
class LineItemSource
{
    public function __construct(
        public string $productName = '',
        public int $quantity = 0,
        #[Map(target: 'amount')]
        public float $price = 0.0,
    ) {
    }
}
