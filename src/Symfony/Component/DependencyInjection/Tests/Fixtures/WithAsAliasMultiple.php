<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: BarInterface::class, public: true)]
#[AsAlias(id: 'some-alias')]
class WithAsAliasMultiple
{
}
