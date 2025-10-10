<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;

return [
    'parameters' => [
        'foo' => 'bar',
    ],
    'services' => [
        '_defaults' => [
            'public' => true,
        ],
        Bar::class => null,
        'my_service' => [
            'class' => Bar::class,
            'arguments' => ['%foo%'],
        ],
    ],
];
