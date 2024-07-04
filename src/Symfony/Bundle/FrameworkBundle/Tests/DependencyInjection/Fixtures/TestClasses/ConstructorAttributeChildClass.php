<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\TestClasses;

class ConstructorAttributeChildClass extends ConstructorAttributeValidClass
{
    public static function childStaticConstructor(): self
    {
        return new self();
    }
}
