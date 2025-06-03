<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

use Symfony\Component\DependencyInjection\Attribute\WhenNot;

#[NeverInProduction]
#[WhenNot(env: 'dev')]
class NotFoo
{
    public function __construct($bar = null, ?iterable $foo = null, ?object $baz = null)
    {
    }

    public function setFoo(self $foo)
    {
    }
}

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::IS_REPEATABLE)]
class NeverInProduction extends WhenNot
{
    public function __construct()
    {
        parent::__construct('prod');
    }
}
