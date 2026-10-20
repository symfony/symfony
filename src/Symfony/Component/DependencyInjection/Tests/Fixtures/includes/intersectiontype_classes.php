<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Attribute\Target;

interface AnotherInterface
{
}

class IntersectionClasses
{
    public function __construct(CollisionInterface&AnotherInterface $collision)
    {
    }
}

class IntersectionClassesWithTarget
{
    public function __construct(
        #[Target('foo')]
        CollisionInterface&AnotherInterface $collision,
    ) {
    }
}
