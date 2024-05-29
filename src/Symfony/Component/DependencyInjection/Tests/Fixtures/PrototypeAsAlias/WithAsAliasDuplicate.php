<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: AliasFooInterface::class)]
class WithAsAliasDuplicate
{
}
