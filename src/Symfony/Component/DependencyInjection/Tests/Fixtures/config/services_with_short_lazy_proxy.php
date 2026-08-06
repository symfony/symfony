<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $configurator) {
    $configurator->services()
        ->set('foo', 'Foo')
        ->args([
            lazy_proxy('bar'),
            lazy_proxy('bar')->ignoreOnInvalid(),
            lazy_proxy('bar')->ignoreOnUninitialized(),
        ]);
};
