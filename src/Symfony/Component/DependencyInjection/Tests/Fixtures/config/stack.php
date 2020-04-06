<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;

return function (ContainerConfigurator $c) {
    $services = $c->services();

    $services->stack('stack_a', [
        service('stdClass')
            ->property('label', 'A')
            ->property('inner', ref('.inner')),
        service('stdClass')
            ->property('label', 'B')
            ->property('inner', ref('.inner')),
        service('stdClass')
            ->property('label', 'C'),
    ])->public();

    $services->stack('stack_abstract', [
        service('stdClass')
            ->property('label', 'A')
            ->property('inner', ref('.inner')),
        service('stdClass')
            ->property('label', 'B')
            ->property('inner', ref('.inner')),
    ]);

    $services->stack('stack_b', [
        ref('stack_abstract'),
        service('stdClass')
            ->property('label', 'C'),
    ])->public();

    $services->stack('stack_c', [
        service('stdClass')
            ->property('label', 'Z')
            ->property('inner', ref('.inner')),
        ref('stack_a'),
    ])->public();

    $services->stack('stack_d', [
        service()
            ->parent('stack_abstract')
            ->property('label', 'Z'),
        service('stdClass')
            ->property('label', 'C'),
    ])->public();
};
