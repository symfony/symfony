<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;

return function (ContainerConfigurator $c) {
    $c->parameters()
        ('foo', 'Foo')
        ('bar', 'Bar')
    ;
    $c->services()->defaults()->public()
        (Foo::class)
            ->arg('$bar', service('bar'))
            ->public()
        ('bar', Foo::class)
            ->call('setFoo')
    ;
};
