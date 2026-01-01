<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

class TransformCollectionWithContext
{
    #[Map(transform: MapCollection::class, context: ['targetClass' => TransformCollectionE::class])]
    /** @var TransformCollectionE[] */
    public array $foo;
}
