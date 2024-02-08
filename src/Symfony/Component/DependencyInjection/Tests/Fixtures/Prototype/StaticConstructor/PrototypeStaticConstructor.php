<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor;

class PrototypeStaticConstructor implements PrototypeStaticConstructorInterface
{
    public static function create(): static
    {
        return new self();
    }
}
