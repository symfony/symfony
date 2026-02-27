<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

return function (ContainerConfigurator $container, PhpFileLoader $loader) {
    $services = $container->services();

    $services->instanceof(\stdClass::class)->tag('foo_tag');

    $services->set('service_before', \stdClass::class);

    $loader->import('instanceof_import_child.php');

    $services->set('service_after', \stdClass::class);
};
