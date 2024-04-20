<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias]
class WithAsAliasMultipleInterface implements AliasFooInterface, AliasBarInterface
{
}
