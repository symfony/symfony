<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\containers;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class CustomContainer extends Container
{
    public function getBarService()
    {
    }

    public function getFoobarService()
    {
    }
}
