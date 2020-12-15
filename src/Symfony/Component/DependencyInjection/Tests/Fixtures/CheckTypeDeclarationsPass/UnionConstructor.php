<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class UnionConstructor
{
    public function __construct(Foo|int $arg)
    {
    }
}
