<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

class ObjectInner
{
    public $foo;
    public $bar;

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar): void
    {
        $this->bar = $bar;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo): void
    {
        $this->foo = $foo;
    }
}
