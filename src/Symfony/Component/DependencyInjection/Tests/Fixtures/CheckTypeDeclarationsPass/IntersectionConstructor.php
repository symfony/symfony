<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class IntersectionConstructor
{
    public function __construct(Foo&WaldoInterface $arg)
    {
    }
}
