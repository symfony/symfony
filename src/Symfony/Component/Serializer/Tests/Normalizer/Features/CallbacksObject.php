<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

class CallbacksObject
{
    public $bar;

    /**
     * @var string|null
     */
    public $foo;

    public function __construct($bar = null, string $foo = null)
    {
        $this->bar = $bar;
        $this->foo = $foo;
    }

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar)
    {
        $this->bar = $bar;
    }

    public function getFoo(): ?string
    {
        return $this->foo;
    }

    public function setFoo(?string $foo)
    {
        $this->foo = $foo;
    }
}
