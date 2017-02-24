<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container32;

use Symfony\Component\DependencyInjection\Argument\ClosureProxyArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (!class_exists(Foo::class, false)) {
    class Foo
    {
        public function withVariadic($a, &...$c)
        {
        }

        public function withNullable(?int $a)
        {
        }

        public function withReturnType(): \Bar
        {
        }
    }
}

$container = new ContainerBuilder();

$container->register('foo', Foo::class);

$container->register('bar', 'stdClass')
    ->setProperty('foo', array(
        new ClosureProxyArgument('foo', 'withVariadic'),
        new ClosureProxyArgument('foo', 'withNullable'),
        new ClosureProxyArgument('foo', 'withReturnType'),
    ))
;

return $container;
