<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_PROPERTY)]
class DummyAttribute
{
    public function __construct(
        public string $type,
        public string $name,
        public int $version,
    ) {
    }
}
