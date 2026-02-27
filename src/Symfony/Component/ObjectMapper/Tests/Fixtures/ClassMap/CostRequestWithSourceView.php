<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Cost;

#[Map(source: Cost::class)]
final class CostRequestWithSourceView
{
    #[Map(source: 'bar')]
    public ?string $foo = null;
}
