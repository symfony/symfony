<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $s = $c->services()->defaults()->public();

    $s->set('foo', 'Bar\FooClass')->constructor('getInstance');
};
