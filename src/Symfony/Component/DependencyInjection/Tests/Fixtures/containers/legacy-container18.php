<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Scope;

$container = new ContainerBuilder();
$container->addScope(new Scope('request'));
$container->
    register('foo', 'FooClass')->
    setScope('request')
;
$container->compile();

return $container;
