<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: 'some-alias', public: true)]
class WithAsAliasPublic
{
}
