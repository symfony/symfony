<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

#[SomeAttribute]
#[MissingClassAttribute]
class AttributeWithMissingUse
{
    #[MissingConstantAttribute]
    public const FOO = 1;

    #[MissingPropertyAttribute]
    public $prop;

    #[\Some\Vendor\MissingAttribute]
    public function method(#[MissingParameterAttribute] $param)
    {
    }
}
