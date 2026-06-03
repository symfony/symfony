<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedMappingWithClassTransformer;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: ChildWithClassTransformTarget::class, transform: [ChildWithClassTransformTarget::class, 'createFromSource'])]
class ChildWithClassTransformSource
{
    public string $name = 'ChildWithClassTransformSource';
}
