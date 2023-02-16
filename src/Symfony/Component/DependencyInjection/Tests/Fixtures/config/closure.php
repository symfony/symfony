<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return new class() {
    public function __invoke(ContainerConfigurator $c)
    {
        $c->services()
            ->set('closure_property', 'stdClass')
                ->public()
                ->property('foo', closure(service('bar')))
            ->set('bar', 'stdClass')
            ->set('closure_of_service_closure', 'stdClass')
                ->public()
                ->args([closure(service_closure('bar2'))])
            ->set('bar2', 'stdClass');
    }
};
