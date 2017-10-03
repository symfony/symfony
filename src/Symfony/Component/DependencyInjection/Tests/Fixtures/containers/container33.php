<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Container33;

use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();

$container->register(\Foo\Foo::class)->setPublic(true);
$container->register(\Bar\Foo::class)->setPublic(true);

return $container;
