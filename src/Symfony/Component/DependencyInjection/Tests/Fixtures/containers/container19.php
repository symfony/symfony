<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

require_once __DIR__.'/../includes/classes.php';

$container = new ContainerBuilder();

$container->setParameter('env(FOO)', 'Bar\FaooClass');
$container->setParameter('foo', '%env(FOO)%');

$container
    ->register('service_from_anonymous_factory', '%foo%')
    ->setFactory([new Definition('%foo%'), 'getInstance'])
    ->setPublic(true)
;

$anonymousServiceWithFactory = new Definition('Bar\FooClass');
$anonymousServiceWithFactory->setFactory('Bar\FooClass::getInstance');
$container
    ->register('service_with_method_call_and_factory', 'Bar\FooClass')
    ->addMethodCall('setBar', [$anonymousServiceWithFactory])
    ->setPublic(true)
;

return $container;
