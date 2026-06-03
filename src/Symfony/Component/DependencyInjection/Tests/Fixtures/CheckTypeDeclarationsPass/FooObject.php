<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class FooObject
{
    public function __construct(object $foo)
    {
    }
}
