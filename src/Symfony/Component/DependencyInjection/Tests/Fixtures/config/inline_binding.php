<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Foo;

return function (ContainerConfigurator $c) {
    $c->import('basic.php');

    $s = $c->services()->defaults()
        ->public()
        ->private()
        ->autoconfigure()
        ->autowire()
        ->tag('t', ['a' => 'b'])
        ->bind(Foo::class, service('bar'))
        ->bind('iterable $foo', tagged_iterator('foo'))
        ->bind('object $baz', inline_service('Baz'))
        ->public();

    $s->set(Foo::class)->args([service('bar')])->public();
    $s->set('bar', Foo::class)->call('setFoo')->autoconfigure(false);
};
