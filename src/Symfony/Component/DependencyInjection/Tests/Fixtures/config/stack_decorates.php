<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $services = $c->services();

    $services->set('original_service', 'stdClass')
        ->public()
        ->property('label', 'original');

    $services->stack('my_stack', [
        inline_service('stdClass')
            ->property('label', 'A')
            ->property('inner', service('.inner')),
        inline_service('stdClass')
            ->property('label', 'B')
            ->property('inner', service('.inner')),
    ])->decorate('original_service');
};
