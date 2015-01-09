<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();
$container
    ->register('request', 'Request')
    ->setSynchronized(true)
;
$container
    ->register('depends_on_request', 'stdClass')
    ->addMethodCall('setRequest', array(new Reference('request', ContainerInterface::NULL_ON_INVALID_REFERENCE, false)))
;

return $container;
