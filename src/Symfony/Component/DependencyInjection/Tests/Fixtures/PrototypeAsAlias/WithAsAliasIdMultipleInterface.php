<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: AliasBarInterface::class)]
class WithAsAliasIdMultipleInterface implements AliasFooInterface, AliasBarInterface
{
}
