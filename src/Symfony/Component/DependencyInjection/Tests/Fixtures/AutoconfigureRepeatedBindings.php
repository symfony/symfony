<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(bind: ['$arg' => 'foo'])]
#[Autoconfigure(bind: ['$arg' => 'bar'])]
class AutoconfigureRepeatedBindings
{
}
