<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

$container = new ContainerBuilder();
$container->setParameter('cla', 'Fo');
$container->setParameter('ss', 'Class');

$definition = new Definition('%cla%o%ss%');
$container->setDefinition('foo', $definition);

return $container;
