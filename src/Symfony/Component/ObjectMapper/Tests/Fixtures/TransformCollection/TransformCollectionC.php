<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\TransformCollection;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: TransformCollectionD::class)]
class TransformCollectionC
{
    public function __construct(
        #[Map(target: 'baz')]
        public string $bar,
    ) {
    }
}
