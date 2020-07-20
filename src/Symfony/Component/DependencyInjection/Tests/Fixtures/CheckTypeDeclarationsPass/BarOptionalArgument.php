<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

class BarOptionalArgument
{
    public $foo;

    public function __construct(\stdClass $foo = null)
    {
        $this->foo = $foo;
    }
}
