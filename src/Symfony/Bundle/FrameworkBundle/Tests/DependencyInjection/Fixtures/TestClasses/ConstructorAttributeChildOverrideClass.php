<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\TestClasses;

use Symfony\Component\DependencyInjection\Attribute\Constructor;

class ConstructorAttributeChildOverrideClass extends ConstructorAttributeValidClass
{
    #[Constructor]
    public static function childStaticConstructor(): self
    {
        return new self();
    }
}
