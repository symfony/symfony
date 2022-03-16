<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooWithAbstractArgument
{
    /** @var string */
    private $baz;

    /** @var string */
    private $bar;

    public function __construct(string $baz, string $bar)
    {
        $this->baz = $baz;
        $this->bar = $bar;
    }
}
