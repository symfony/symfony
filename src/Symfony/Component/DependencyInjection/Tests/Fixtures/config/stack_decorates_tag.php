<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return function (ContainerConfigurator $c) {
    $services = $c->services();

    $services->set('foo', 'stdClass')
        ->public()
        ->tag('my_tag')
        ->property('label', 'foo');

    $services->set('bar', 'stdClass')
        ->public()
        ->tag('my_tag')
        ->property('label', 'bar');

    $services->stack('my_stack', [
        inline_service('stdClass')
            ->property('label', 'A')
            ->property('inner', service('.inner')),
        inline_service('stdClass')
            ->property('label', 'B')
            ->property('inner', service('.inner')),
    ])->decorateTag('my_tag');
};
