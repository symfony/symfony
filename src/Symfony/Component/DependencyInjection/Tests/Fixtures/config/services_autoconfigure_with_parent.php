<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $c->services()
        ->set('parent_service', \stdClass::class)
        ->set('child_service')->parent('parent_service')->autoconfigure(true);
};
