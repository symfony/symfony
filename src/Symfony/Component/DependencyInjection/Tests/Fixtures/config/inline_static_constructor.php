<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor\PrototypeStaticConstructor;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\StaticConstructor\PrototypeStaticConstructorAsArgument;

return function (ContainerConfigurator $c) {
    $s = $c->services()->defaults()->public();
    $s->set('foo', PrototypeStaticConstructorAsArgument::class)
        ->args(
            [inline_service(PrototypeStaticConstructor::class)
                ->constructor('create')]
        );
};
