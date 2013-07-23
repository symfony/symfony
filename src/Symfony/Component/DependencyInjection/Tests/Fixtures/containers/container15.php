<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

$container = new ContainerBuilder();
$container->addScope(new \Symfony\Component\DependencyInjection\Scope('request'));
$container
    ->register('synchronizedService', 'BarClass')
    ->setSynchronized(true)
    ->setScope('request')
;
$container
    ->register('dependsOnSynchronized', 'FooClass')
    ->addMethodCall('setBar', array(new Reference('synchronizedService', ContainerInterface::NULL_ON_INVALID_REFERENCE, false)))
;

return $container;
