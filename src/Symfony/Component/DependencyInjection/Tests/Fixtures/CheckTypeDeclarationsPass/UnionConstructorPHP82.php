<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class UnionConstructorPHP82
{
    public static function createTrue(array|true $arg): static
    {
        return new static(0);
    }

    public static function createNull(null $arg): static
    {
        return new static(0);
    }
}
