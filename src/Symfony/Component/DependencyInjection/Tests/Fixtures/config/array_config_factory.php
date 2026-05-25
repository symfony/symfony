<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarFactory;

return App::config([
    'services' => [
        '_defaults' => [
            'public' => true,
        ],
        BarFactory::class => [
            'arguments' => [[]],
        ],
        'invokable_factory' => [
            'class' => Bar::class,
            'factory' => service(BarFactory::class),
        ],
        'array_factory' => [
            'class' => Bar::class,
            'factory' => [service(BarFactory::class), 'getDefaultBar'],
        ],
        'invokable_configurator' => [
            'class' => Bar::class,
            'configurator' => service(BarFactory::class),
        ],
    ],
]);
