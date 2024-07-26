<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

return function (ContainerConfigurator $c) {
    $di = $c->services()->defaults()
        ->tag('baz');

    $di->load(Prototype::class.'\\', '../Prototype')
        ->onlyWithServiceAttribute()
        ->public()
        ->autoconfigure()
        ->exclude('../Prototype/{BadClasses,BadAttributes}')
        ->factory('f')
        ->deprecate('vendor/package', '1.1', '%service_id%')
        ->args([0])
        ->args([1])
        ->tag('foo')
        ->parent('foo');

    $di->set('foo')->lazy()->abstract()->public();
};
