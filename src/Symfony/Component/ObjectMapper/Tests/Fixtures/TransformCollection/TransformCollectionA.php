<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Transform\MapCollection;

class TransformCollectionA
{
    #[Map(transform: new MapCollection())]
    /** @var TransformCollectionC[] */
    public array $foo;
}
