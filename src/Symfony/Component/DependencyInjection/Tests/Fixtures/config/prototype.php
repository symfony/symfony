<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype;

return function (ContainerConfigurator $c) {
    $di = $c->services()->defaults()
        ->tag('baz');
    $di->load(Prototype::class.'\\', '../Prototype')
        ->autoconfigure()
        ->exclude('../Prototype/{OtherDir,BadClasses}')
        ->factory('f')
        ->deprecate('%service_id%')
        ->args(array(0))
        ->args(array(1))
        ->autoconfigure(false)
        ->tag('foo')
        ->parent('foo');
    $di->set('foo')->lazy()->abstract();
    $di->get(Prototype\Foo::class)->lazy(false);
};
