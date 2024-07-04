<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\TestClasses;

use Symfony\Component\DependencyInjection\Attribute\Constructor;

class ConstructorAttributeMultipleOnSameClass
{
    #[Constructor]
    public function staticConstructor(): self
    {
        return new self();
    }

    #[Constructor]
    public function staticConstructor2(): self
    {
        return new self();
    }
}
