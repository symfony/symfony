<?php

namespace Symphony\Component\DependencyInjection\Tests\Fixtures;

class NamedArgumentsVariadicsDummy
{
    public function __construct(\stdClass $class, ...$variadics)
    {
    }
}
