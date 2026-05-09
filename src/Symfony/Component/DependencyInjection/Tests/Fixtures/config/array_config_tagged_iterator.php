<?php

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return [
    'services' => [
        '_defaults' => [
            'public' => true,
            'autowire' => true,
            'bind' => [
                'iterable $foo' => tagged_iterator('app.rule'),
            ],
        ],
        Foo::class => null,
        'bar' => [
            'class' => Foo::class,
            'arguments' => [
                '$foo' => tagged_iterator('app.handler'),
                '$baz' => tagged_locator('app.handler'),
            ],
        ],
    ],
];
