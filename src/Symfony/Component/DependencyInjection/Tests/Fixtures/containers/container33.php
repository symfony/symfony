<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container33;

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container->register(\Foo\Foo::class);
$container->register(\Bar\Foo::class);

return $container;
