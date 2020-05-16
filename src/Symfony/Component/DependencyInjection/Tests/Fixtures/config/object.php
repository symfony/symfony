<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\BarService;

return new class() {
    public function __invoke(ContainerConfigurator $c)
    {
        $s = $c->services()->defaults()->public();
        $s->set(BarService::class)
            ->args([inline_service('FooClass')]);
    }
};
