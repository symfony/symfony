<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLoadedValue;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: LoadedValue::class, transform: ServiceLoadedValueTransformer::class)]
class ValueToMapRelation
{
    public function __construct(public string $name)
    {
    }
}
