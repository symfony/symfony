<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\PrototypeAutoconfigure;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(
    tags: ['prototype_autoconfigure'],
    calls: [
        ['addCall' => ['from_interface']],
    ],
)]
interface AutoconfiguredInterface
{
}
