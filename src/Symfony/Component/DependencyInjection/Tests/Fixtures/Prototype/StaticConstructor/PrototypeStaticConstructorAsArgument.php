<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor;

class PrototypeStaticConstructorAsArgument
{
    public function __construct(private PrototypeStaticConstructor $prototypeStaticConstructor)
    {
    }
}
