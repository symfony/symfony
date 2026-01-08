<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapCollectionAttribute;

/**
 * Source item class without a Map attribute - requires explicit itemClass in MapCollection.
 */
class OrderItem
{
    public function __construct(
        public string $name,
        public int $quantity,
    ) {
    }
}
