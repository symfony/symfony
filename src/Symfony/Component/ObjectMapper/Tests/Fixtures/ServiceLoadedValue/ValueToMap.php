<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLoadedValue;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: LoadedValueTarget::class)]
final class ValueToMap
{
    public ?ValueToMapRelation $relation;
}
