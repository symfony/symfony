<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

class DummyObjectWithUnionEnumConstructor
{
    public function __construct(public StringBackedEnumDummy|IntegerBackedEnumDummy $sub)
    {
    }
}
