<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

require_once __DIR__.'/../includes/classes.php';

$container = new ContainerBuilder();

$container
    ->register('service_from_anonymous_factory', 'Bar\FooClass')
    ->setFactory(array(new Definition('Bar\FooClass'), 'getInstance'))
    ->setPublic(true)
;

$anonymousServiceWithFactory = new Definition('Bar\FooClass');
$anonymousServiceWithFactory->setFactory('Bar\FooClass::getInstance');
$container
    ->register('service_with_method_call_and_factory', 'Bar\FooClass')
    ->addMethodCall('setBar', array($anonymousServiceWithFactory))
    ->setPublic(true)
;

return $container;
