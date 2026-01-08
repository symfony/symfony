<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\MapCollectionAttribute;

/**
 * Target order DTO.
 */
class OrderDto
{
    public int $id;

    /** @var OrderItemDto[] */
    public array $items = [];
}
