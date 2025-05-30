<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarFactory;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarInterface;

return [
    'services' => [
        '_defaults' => [
            'public' => true,
        ],
        '_instanceof' => [
            BarInterface::class => [
                'factory' => [service(BarFactory::class), 'getDefaultBar'],
                'tags' => ['bar'],
            ],
        ],
        Bar::class => null,
        BarFactory::class => [
            'arguments' => [tagged_iterator('bar')],
        ],
    ],
];
