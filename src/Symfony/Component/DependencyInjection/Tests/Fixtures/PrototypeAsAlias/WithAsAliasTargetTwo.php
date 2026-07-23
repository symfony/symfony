<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAsAlias;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AliasFooInterface::class, target: 'two')]
final class WithAsAliasTargetTwo implements AliasFooInterface
{
}
