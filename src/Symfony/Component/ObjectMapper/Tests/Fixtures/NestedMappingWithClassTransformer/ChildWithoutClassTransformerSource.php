<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: ChildWithoutClassTransformerTarget::class)]
class ChildWithoutClassTransformerSource
{
    public string $name = 'ChildWithoutClassTransformerSource';
}
