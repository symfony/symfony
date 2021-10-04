<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class NewInInitializer
{
    public function __construct($foo = new \stdClass(), $bar = 123)
    {
    }
}
