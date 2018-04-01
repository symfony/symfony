<?php

namespace Symphony\Component\DependencyInjection\Loader\Configurator;

use Symphony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;

return function (ContainerConfigurator $c) {
    $c->parameters()
        ('foo', 'Foo')
        ('bar', 'Bar')
    ;
    $c->services()
        (Foo::class)
            ->arg('$bar', ref('bar'))
            ->public()
        ('bar', Foo::class)
            ->call('setFoo')
    ;
};
