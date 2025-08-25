<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()->inheritConfiguration(false);

    $services->set('foo', 'Foo');
    $services->set('bar', 'Bar')->inheritConfiguration();
};
