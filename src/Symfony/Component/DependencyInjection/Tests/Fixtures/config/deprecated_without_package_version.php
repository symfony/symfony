<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $c->services()
        ->set('foo', 'stdClass')
        ->deprecate('%service_id%')
    ;
};
