<?php

namespace Symfony\Component\VarDumper\Tests\Fixtures;

class GeneratorDemo
{
    public static function foo()
    {
        yield 1;
    }

    public function baz(): void
    {
        yield from bar();
    }
}

function bar(): void
{
    yield from GeneratorDemo::foo();
}
