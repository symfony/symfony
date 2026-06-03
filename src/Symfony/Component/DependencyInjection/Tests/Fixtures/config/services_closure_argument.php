<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $s = $c->services()->defaults()->public();

    $s->set('foo', 'Foo');

    $s->set('service_closure', 'Bar')
        ->args([service_closure('foo')]);

    $s->set('service_closure_invalid', 'Bar')
        ->args([service_closure('invalid_service')->nullOnInvalid()]);
};
