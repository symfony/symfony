<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: AliasFooInterface::class, public: true)]
#[AsAlias(id: 'some-alias')]
class WithAsAliasMultiple
{
}
