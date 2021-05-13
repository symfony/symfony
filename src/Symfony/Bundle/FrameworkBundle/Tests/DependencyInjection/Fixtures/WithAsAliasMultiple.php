<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: FooInterface::class, public: true)]
#[AsAlias(id: 'some-alias')]
class WithAsAliasMultiple
{
}
