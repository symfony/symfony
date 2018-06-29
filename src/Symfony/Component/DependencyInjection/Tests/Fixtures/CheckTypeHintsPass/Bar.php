<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeHintsPass;

class Bar
{
    public $foo;

    public function __construct(\stdClass $foo)
    {
        $this->foo = $foo;
    }
}
