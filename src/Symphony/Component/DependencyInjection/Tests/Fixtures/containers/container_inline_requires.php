<?php

namespace Symphony\Tests\InlineRequires;

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Dumper\PhpDumper;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\Tests\Fixtures\includes\HotPath;
use Symphony\Component\DependencyInjection\Tests\Fixtures\ParentNotExists;

$container = new ContainerBuilder();

$container->register(HotPath\C1::class)->addTag('container.hot_path')->setPublic(true);
$container->register(HotPath\C2::class)->addArgument(new Reference(HotPath\C3::class))->setPublic(true);
$container->register(HotPath\C3::class);
$container->register(ParentNotExists::class)->setPublic(true);

return $container;
