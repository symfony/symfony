<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\InheritedClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Tests\Fixtures\MapStruct\Target;
use Symfony\Component\ObjectMapper\Tests\Fixtures\NestedCollectionMapping\LineItemSource;

#[Map(target: Target::class)]
class OverridingChildOfLineItemSource extends LineItemSource
{
}
