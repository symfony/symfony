<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

class IgnoreDummyAdditionalGetterWithoutIgnoreAnnotations
{
    private $myValue;

    public function getMyValue()
    {
        return $this->myValue;
    }

    public function getExtraValue(string $parameter)
    {
        return $parameter;
    }

    public function setExtraValue2(string $parameter)
    {
    }
}
