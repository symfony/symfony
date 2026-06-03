<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $services = $c->services()->defaults()->public();

    $services
        ->set('foo', FooService::class)
        ->remove('foo')

        ->set('baz', BazService::class)
        ->alias('baz-alias', 'baz')
        ->remove('baz-alias')

        ->remove('bat'); // noop
};
