<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapCollectionAttribute;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Attribute\MapCollection;

/**
 * Source order class using MapCollection attribute with itemClass.
 */
#[Map(target: OrderDto::class)]
class Order
{
    public int $id;

    /** @var OrderItem[] */
    #[MapCollection(itemClass: OrderItemDto::class)]
    public array $items = [];
}
