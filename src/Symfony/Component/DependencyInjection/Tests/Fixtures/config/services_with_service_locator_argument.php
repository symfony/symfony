<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()->defaults()->public();

    $services->set('foo_service', \stdClass::class);

    $services->set('bar_service', \stdClass::class);

    $services->set('locator_dependent_service_indexed', \ArrayObject::class)
        ->args([service_locator([
            'foo' => service('foo_service'),
            'bar' => service('bar_service'),
        ])]);

    $services->set('locator_dependent_service_not_indexed', \ArrayObject::class)
        ->args([service_locator([
            service('foo_service'),
            service('bar_service'),
        ])]);

    $services->set('locator_dependent_service_mixed', \ArrayObject::class)
        ->args([service_locator([
            'foo' => service('foo_service'),
            service('bar_service'),
        ])]);
};
