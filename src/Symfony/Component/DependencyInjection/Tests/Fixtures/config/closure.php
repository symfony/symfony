<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return new class() {
    public function __invoke(ContainerConfigurator $c)
    {
        $c->services()
            ->set('closure_property', 'stdClass')
                ->public()
                ->property('foo', closure(service('bar')))
            ->set('bar', 'stdClass');
    }
};
