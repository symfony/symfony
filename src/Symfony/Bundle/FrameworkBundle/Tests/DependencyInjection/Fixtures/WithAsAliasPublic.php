<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: 'some-alias', public: true)]
class WithAsAliasPublic
{
}
