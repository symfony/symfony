<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(properties: ['$replaced' => 'to be replaced', '$bar' => 'existing to be replaced'])]
#[Autoconfigure(properties: ['$foo' => 'bar', '$bar' => 'baz'])]
class AutoconfigureRepeatedProperties
{
}
