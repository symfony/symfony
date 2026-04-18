<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

#[Map(source: QuoteWithToys::class)]
final class QuoteWithToysRequestView
{
    public string $id;

    /** @var list<CostRequestView> */
    #[Map(transform: new MapCollection(targetClass: CostRequestView::class))]
    public array $toys = [];
}
