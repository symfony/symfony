<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

class ConstructorArgumentsObject
{
    private $foo;
    private $bar;
    private $baz;

    public function __construct($foo, $bar, $baz)
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
    }
}
