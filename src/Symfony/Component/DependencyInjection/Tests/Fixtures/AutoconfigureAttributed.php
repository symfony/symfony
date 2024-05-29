<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(
    lazy: true,
    public: true,
    autowire: true,
    shared: true,
    properties: [
        'bar' => 'baz',
    ],
    configurator: '@bla',
    tags: [
        'a_tag',
        ['another_tag' => ['attr' => 234]],
    ],
    calls: [
        ['setBar' => [2, 3]]
    ],
    bind: [
        '$bar' => 1,
    ],
    constructor: 'create'
)]
class AutoconfigureAttributed
{
}
