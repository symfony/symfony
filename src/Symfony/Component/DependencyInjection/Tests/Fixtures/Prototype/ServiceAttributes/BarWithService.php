<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\ServiceAttributes;

use Symfony\Component\DependencyInjection\Attribute\Service;

#[Service]
class BarWithService
{
    public function __construct($bar = null, ?iterable $foo = null, ?object $baz = null)
    {
    }

    public function setFoo(self $foo)
    {
    }
}
