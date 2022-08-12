<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Annotation\Ignore;

class IgnoreDummyAdditionalGetter
{
    private $myValue;

    #[Ignore]
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
