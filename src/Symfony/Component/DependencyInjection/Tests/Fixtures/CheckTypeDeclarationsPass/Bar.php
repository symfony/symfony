<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class Bar
{
    public $foo;

    public function __construct(\stdClass $foo)
    {
        $this->foo = $foo;
    }
}
