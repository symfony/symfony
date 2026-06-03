<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor;

interface PrototypeStaticConstructorInterface
{
    public static function create(): static;
}
