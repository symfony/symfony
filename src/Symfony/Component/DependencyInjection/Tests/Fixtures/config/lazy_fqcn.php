<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $di = $c->services();
    $di->set('foo', 'stdClass')->lazy('SomeInterface');
};
