<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: FooInterface::class)]
class WithAsAlias
{
}
