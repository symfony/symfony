<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

#[Lazy, Autoconfigure(lazy: true)]
class LazyAutoconfigured
{
}
