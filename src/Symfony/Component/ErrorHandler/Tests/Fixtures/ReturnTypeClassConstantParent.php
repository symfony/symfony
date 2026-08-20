<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

abstract class ReturnTypeClassConstantParent
{
    /**
     * @return \Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeClassConstantHolder::FOO
     */
    public function classConstantOfSubClass()
    {
    }
}
