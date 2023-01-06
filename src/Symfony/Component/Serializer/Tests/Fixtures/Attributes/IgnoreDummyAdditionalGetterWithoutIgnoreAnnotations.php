<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

class IgnoreDummyAdditionalGetterWithoutIgnoreAnnotations
{
    private $myValue;

    public function getIgnored2()
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
