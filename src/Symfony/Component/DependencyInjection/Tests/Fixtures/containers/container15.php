<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

$container = new ContainerBuilder();
$container->addScope(new \Symfony\Component\DependencyInjection\Scope('request'));
$container
    ->register('synchronized_service', 'BarClass')
    ->setSynchronized(true)
    ->setScope('request')
;
$container
    ->register('depends_on_synchronized', 'FooClass')
    ->addMethodCall('setBar', array(new Reference('synchronized_service', ContainerInterface::NULL_ON_INVALID_REFERENCE, false)))
;

return $container;
