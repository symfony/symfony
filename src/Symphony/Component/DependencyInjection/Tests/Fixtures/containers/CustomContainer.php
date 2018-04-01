<?php

namespace Symphony\Component\DependencyInjection\Tests\Fixtures\containers;

use Symphony\Component\DependencyInjection\Container;
use Symphony\Component\DependencyInjection\ParameterBag\ParameterBag;

class CustomContainer extends Container
{
    public function getBarService()
    {
    }

    public function getFoobarService()
    {
    }
}
