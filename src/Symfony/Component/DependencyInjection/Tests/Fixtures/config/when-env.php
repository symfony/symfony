<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\BarService;

return function (ContainerConfigurator $c) {
    $c->parameters()
        ->set('foo', 123);

    $c->when('some-env')
        ->parameters()
            ->set('foo', 234)
            ->set('bar', 345);

    $c->when('some-other-env')
        ->parameters()
            ->set('foo', 456)
            ->set('baz', 567);
};
