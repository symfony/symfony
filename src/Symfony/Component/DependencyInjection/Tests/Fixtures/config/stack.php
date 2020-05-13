<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;

return function (ContainerConfigurator $c) {
    $services = $c->services();

    $services->stack('stack_a', [
        inline_service('stdClass')
            ->property('label', 'A')
            ->property('inner', service('.inner')),
        inline_service('stdClass')
            ->property('label', 'B')
            ->property('inner', service('.inner')),
        inline_service('stdClass')
            ->property('label', 'C'),
    ])->public();

    $services->stack('stack_abstract', [
        inline_service('stdClass')
            ->property('label', 'A')
            ->property('inner', service('.inner')),
        inline_service('stdClass')
            ->property('label', 'B')
            ->property('inner', service('.inner')),
    ]);

    $services->stack('stack_b', [
        service('stack_abstract'),
        inline_service('stdClass')
            ->property('label', 'C'),
    ])->public();

    $services->stack('stack_c', [
        inline_service('stdClass')
            ->property('label', 'Z')
            ->property('inner', service('.inner')),
        service('stack_a'),
    ])->public();

    $services->stack('stack_d', [
        inline_service()
            ->parent('stack_abstract')
            ->property('label', 'Z'),
        inline_service('stdClass')
            ->property('label', 'C'),
    ])->public();
};
