<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $s = $c->services()->defaults()->public();

    $s->set('foo', 'Bar\FooClass');
    $s->set('bar', 'Bar\FooClass')->factory(expr('service("foo").getInstance()'));
};
