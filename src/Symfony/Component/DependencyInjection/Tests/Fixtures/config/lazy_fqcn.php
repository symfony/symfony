<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $di = $c->services()->defaults()->public();
    $di->set('foo', 'stdClass')->lazy('SomeInterface');
};
