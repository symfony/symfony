<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

final class StdClassDecorator
{
    public function __construct(\stdClass $foo)
    {
        $this->foo = $foo;
    }
}
