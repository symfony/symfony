<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass;

class BarOptionalArgument
{
    public $foo;

    public function __construct(\stdClass $foo = null)
    {
        $this->foo = $foo;
    }
}
