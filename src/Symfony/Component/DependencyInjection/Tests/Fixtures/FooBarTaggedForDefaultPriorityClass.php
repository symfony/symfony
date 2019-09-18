<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooBarTaggedForDefaultPriorityClass
{
    private $param;

    public function __construct($param = [])
    {
        $this->param = $param;
    }

    public function getParam()
    {
        return $this->param;
    }
}
