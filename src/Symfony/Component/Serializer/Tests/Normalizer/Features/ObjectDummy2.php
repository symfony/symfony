<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;

class ObjectDummy2
{
    public $bar;

    //simulate typed property access
    public function getFakeUninitializedProperty()
    {
        throw new UninitializedPropertyException();
    }
}
