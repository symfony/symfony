<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(tags: ['foo'])]
#[Autoconfigure(tags: ['bar'])]
class MultipleAutoconfigureAttributed
{

}
