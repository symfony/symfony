<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return new class() {
    public function __invoke(ContainerConfigurator $c)
    {
        $c->services()
            ->set('from_callable', 'stdClass')
                ->fromCallable([service('bar'), 'do'])
                ->public()
            ->set('bar', 'stdClass');
    }
};
