<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\NestedConstructedTarget;

final class InnerMapped
{
    public readonly string $slug;

    public function __construct(
        public readonly string $name,
    ) {
        $this->slug = 'slug-of-'.$name;
    }
}
