<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

class CallbacksObject
{
    public $bar;

    public function __construct($bar = null)
    {
        $this->bar = $bar;
    }

    public function getBar()
    {
        return $this->bar;
    }
}
