<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services();

    $services->set('foo_service', \stdClass::class);

    $services->set('bar_service', \ArrayObject::class)
        ->args([
            lazy_proxy('foo_service'),
            lazy_proxy('foo_service', 'SomeInterface'),
            lazy_proxy('foo_service', ['SomeInterface', 'AnotherInterface']),
        ]);
};
