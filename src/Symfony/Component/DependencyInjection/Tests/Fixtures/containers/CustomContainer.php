<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\containers;

use Symfony\Component\DependencyInjection\Container;

class CustomContainer extends Container
{
    public function getBarService()
    {
    }

    public function getFoobarService()
    {
    }
}
