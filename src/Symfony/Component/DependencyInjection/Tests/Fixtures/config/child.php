<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\BarService;

return function (ContainerConfigurator $c) {
    $c->services()
        ->set('bar', 'Class1')->public()
        ->set(BarService::class)
            ->public()
            ->abstract(true)
            ->lazy()
        ->set('foo')
            ->parent(BarService::class)
            ->public()
            ->decorate('bar', 'b', 1)
            ->args([service('b')])
            ->class('Class2')
            ->file('file.php')
            ->parent('bar')
            ->parent(BarService::class)
    ;
};
