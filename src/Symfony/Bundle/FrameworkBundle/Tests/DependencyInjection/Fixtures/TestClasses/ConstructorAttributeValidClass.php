<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\TestClasses;

use Symfony\Component\DependencyInjection\Attribute\Constructor;

class ConstructorAttributeValidClass
{
    #[Constructor]
    public static function staticConstructor(): self
    {
        return new self();
    }
}
