<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class UnionConstructor
{
    public function __construct(Foo|int $arg)
    {
    }

    public static function create(array|false $arg): static
    {
        return new static(0);
    }

    public static function make(mixed $arg): static
    {
        return new static(0);
    }
}
