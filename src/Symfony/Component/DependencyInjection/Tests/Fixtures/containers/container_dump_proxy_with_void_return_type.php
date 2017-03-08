<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\ContainerVoid;

use Symfony\Component\DependencyInjection\Argument\ClosureProxyArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (!class_exists(Foo::class, false)) {
    class Foo
    {
        public function withVoid(): void
        {
        }
    }
}

$container = new ContainerBuilder();

$container->register('foo', Foo::class);

$container->register('bar', 'stdClass')
    ->setProperty('foo', array(
        new ClosureProxyArgument('foo', 'withVoid'),
    ))
;

return $container;
