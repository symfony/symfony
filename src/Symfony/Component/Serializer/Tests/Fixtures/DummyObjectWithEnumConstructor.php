<?php

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Tests\Fixtures\StringBackedEnumDummy;

class DummyObjectWithEnumConstructor
{
    public function __construct(public StringBackedEnumDummy $get)
    {
    }
}
