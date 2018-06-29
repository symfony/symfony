<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass;

class BarOptionalArgumentNotNull
{
    public $foo;

    public function __construct(int $foo = 1)
    {
        $this->foo = $foo;
    }
}
