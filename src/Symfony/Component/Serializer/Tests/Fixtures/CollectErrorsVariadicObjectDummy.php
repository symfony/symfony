<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

class CollectErrorsVariadicObjectDummy
{
    public $foo;

    /** @var CollectErrorsVariadicObjectDummy[]  */
    public $args;

    public function __construct(int $foo, CollectErrorsVariadicObjectDummy ...$args)
    {
        $this->foo = $foo;
        $this->args = $args;
    }
}
