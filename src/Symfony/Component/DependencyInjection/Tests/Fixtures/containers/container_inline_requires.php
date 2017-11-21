<?php

namespace Symfony\Tests\InlineRequires;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath;

$container = new ContainerBuilder();

$container->register(HotPath\C1::class)->addTag('container.hot_path')->setPublic(true);
$container->register(HotPath\C2::class)->addArgument(new Reference(HotPath\C3::class))->setPublic(true);
$container->register(HotPath\C3::class);

return $container;
