<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container33;

use Symfony\Component\DependencyInjection\ContainerBuilder;

if (!class_exists(Foo::class, false)) {
    class Foo
    {
    }
}

$container = new ContainerBuilder();

$container->register(Foo::class);

return $container;
