<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;
use Symfony\Config\ServicesConfig;

return [
    'when@prod' => [
        'parameters' => [
            'foo_param' => 'bar_value',
        ],
        new ServicesConfig(
            defaults: [
                'public' => true,
            ],
            services: [
                Bar::class => null,
                'my_service' => [
                    'class' => Bar::class,
                    'arguments' => ['%foo_param%'],
                ],
            ]
        ),
    ],
];
