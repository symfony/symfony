<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(source: Cost::class)]
final class CostRequestWithSourceView
{
    #[Map(source: 'bar')]
    public ?string $foo = null;
}
