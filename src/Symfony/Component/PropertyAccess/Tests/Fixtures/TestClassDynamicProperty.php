<?php

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestClassDynamicProperty
{
    public function __construct($dynamicProperty)
    {
        $this->dynamicProperty = $dynamicProperty;
    }
}
