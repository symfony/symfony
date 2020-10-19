<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class BarMethodCall
{
    public $foo;

    public function setBar(Bar $bar)
    {
    }

    public function setFoo(\stdClass $foo)
    {
        $this->foo = $foo;
    }

    public function setFoosVariadic(Foo $foo, Foo ...$foos)
    {
        $this->foo = $foo;
    }

    public function setFoosOptional(Foo $foo, Foo $fooOptional = null)
    {
        $this->foo = $foo;
    }

    public function setScalars(int $int, string $string, bool $bool = false)
    {
    }

    public function setArray(array $array)
    {
    }

    public function setIterable(iterable $iterable)
    {
    }

    public function setCallable(callable $callable): void
    {
    }

    public function setClosure(\Closure $closure): void
    {
    }

    public function setString(string $string)
    {
    }
}
