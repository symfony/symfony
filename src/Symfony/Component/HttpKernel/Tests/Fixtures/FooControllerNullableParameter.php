<?php

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

class FooControllerNullableParameter
{
    public function requiredParamAction(\DateTime $param)
    {
    }

    public function defaultParamAction(\DateTime $param = null)
    {
    }

    public function nullableParamAction(?\DateTime $param)
    {
    }
}
