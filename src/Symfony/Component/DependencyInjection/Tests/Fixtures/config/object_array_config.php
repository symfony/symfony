<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\Bar;
use Symfony\Config\ServicesConfig;

return new ServicesConfig(
    parameters: [
        'foo' => 'bar',
    ],
    defaults: [
        'public' => true,
    ],
    services: [
        Bar::class => null,
        'my_service' => [
            'class' => Bar::class,
            'arguments' => ['%foo%'],
        ],
]);
