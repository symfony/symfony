<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

return function (ContainerConfigurator $c) {
    $di = $c->services()->defaults()
        ->tag('baz');
    $di->load(Prototype::class.'\\', '../Prototype')
        ->public()
        ->autoconfigure()
        ->exclude(['../Prototype/OtherDir', '../Prototype/BadClasses', '../Prototype/SinglyImplementedInterface', '../Prototype/StaticConstructor'])
        ->factory('f')
        ->deprecate('vendor/package', '1.1', '%service_id%')
        ->args([0])
        ->args([1])
        ->autoconfigure(false)
        ->tag('foo')
        ->parent('foo');
    $di->set('foo')->lazy()->abstract()->public();
    $di->get(Prototype\Foo::class)->lazy(false);
};
