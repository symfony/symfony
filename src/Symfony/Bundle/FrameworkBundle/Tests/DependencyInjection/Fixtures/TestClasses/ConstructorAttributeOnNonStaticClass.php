<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\TestClasses;

use Symfony\Component\DependencyInjection\Attribute\Constructor;

class ConstructorAttributeOnNonStaticClass
{
    #[Constructor]
    public function nonStaticConstructor(): self
    {
        return new self();
    }
}
