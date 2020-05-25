<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\BarService;

return function (ContainerConfigurator $c) {
    $s = $c->services()->defaults()->public();
    $s->set(BarService::class)
        ->args([inline_service('FooClass')]);
};
