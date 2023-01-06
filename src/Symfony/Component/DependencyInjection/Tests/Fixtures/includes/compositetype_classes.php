<?php

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

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
