<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

/**
 * A target class with both explicit #[Map] attribute and auto-mapped properties.
 */
#[Map(source: Cost::class)]
final class CostRequestWithSourceAndAutoMappedView
{
    public ?int $amount = null;  // Should be auto-mapped (same name as source)
    public ?int $tax = null;     // Should be auto-mapped (same name as source)

    #[Map(source: 'bar')]
    public ?string $foo = null;  // Explicit mapping from 'bar' to 'foo'
}
