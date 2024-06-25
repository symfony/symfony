<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooClassWithFlags
{
    public const FLAG_A = 2;
    public const FLAG_B = 4;
    public const FLAG_C = 8;

    public function __construct(public int $flags)
    {
    }
}
