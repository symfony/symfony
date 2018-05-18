<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\BarService;

return new class() {
    public function __invoke(ContainerConfigurator $c)
    {
        $s = $c->services();
        $s->set(BarService::class)
            ->args(array(inline('FooClass')));
    }
};
