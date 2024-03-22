<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AttributeFixtures;

use Symfony\Component\Routing\Attribute\Route;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class ExtendedRoute extends Route
{
    public function __construct(array|string $path = null, ?string $name = null, array $defaults = [])
    {
        parent::__construct("/{section<(foo|bar|baz)>}" . $path, $name, [], [], array_merge(['section' => 'foo'], $defaults));
    }
}
