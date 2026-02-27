<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;

return [
    'when@prod' => [
        'parameters' => [
            'foo_param' => 'bar_value',
        ],
        'services' => [
            '_defaults' => [
                'public' => true,
            ],
            Bar::class => null,
            'my_service' => [
                'class' => Bar::class,
                'arguments' => ['%foo_param%'],
            ],
        ],
    ],
];
