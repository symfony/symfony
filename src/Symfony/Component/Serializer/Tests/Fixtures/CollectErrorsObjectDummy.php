<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

class CollectErrorsObjectDummy
{
    public $foo;
    public $bar;
    public $baz;

    public function __construct(int $foo, int $bar, int $baz)
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
    }
}
