<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

interface YetAnotherInterface
{
}

class CompositeTypeClasses
{
    public function __construct((CollisionInterface&AnotherInterface)|YetAnotherInterface $collision)
    {
    }
}

class NullableIntersection
{
    public function __construct((CollisionInterface&AnotherInterface)|null $a)
    {
    }
}

class AAndIInterfaceImplementation implements AInterface, IInterface
{
}

class NullableIntersectionLazyConsumer
{
    public function __construct(
        #[Autowire(lazy: true)]
        public (AInterface&IInterface)|null $foo,
    ) {
    }
}
