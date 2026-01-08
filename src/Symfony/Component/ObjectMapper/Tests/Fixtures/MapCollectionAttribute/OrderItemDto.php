<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapCollectionAttribute;

/**
 * Target item DTO.
 */
class OrderItemDto
{
    public function __construct(
        public string $name,
        public int $quantity,
    ) {
    }
}
