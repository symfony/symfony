<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor\PrototypeStaticConstructor;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor\PrototypeStaticConstructorInterface;

return function (ContainerConfigurator $c) {
    $s = $c->services()->defaults()->public();
    $s->instanceof(PrototypeStaticConstructorInterface::class)
        ->constructor('create');

    $s->set('foo', PrototypeStaticConstructor::class);
};
