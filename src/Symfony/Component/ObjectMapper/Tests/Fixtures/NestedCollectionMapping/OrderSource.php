<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedCollectionMapping;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

#[Map(target: OrderTarget::class)]
class OrderSource
{
    #[Map(transform: new MapCollection(targetClass: LineItemTarget::class))]
    public array $items = [];
}
