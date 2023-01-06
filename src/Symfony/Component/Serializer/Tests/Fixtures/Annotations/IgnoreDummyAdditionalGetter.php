<?php

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

use Symfony\Component\Serializer\Annotation\Ignore;

class IgnoreDummyAdditionalGetter
{
    private $myValue;

    /**
     * @Ignore()
     */
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
