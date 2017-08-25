<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();
$container->
    register('foo', 'Bar\FooClass')->
    addTag('foo', array('foo' => 'foo'))->
    addTag('foo', array('bar' => 'bar'))->
    setFactoryClass('Bar\\FooClass')->
    setFactoryMethod('getInstance')->
    setArguments(array('foo', new Reference('foo.baz'), array('%foo%' => 'foo is %foo%', 'foobar' => '%foo%'), true, new Reference('service_container')))->
    setProperties(array('foo' => 'bar', 'moo' => new Reference('foo.baz'), 'qux' => array('%foo%' => 'foo is %foo%', 'foobar' => '%foo%')))->
    addMethodCall('setBar', array(new Reference('bar')))->
    addMethodCall('initialize')->
    setConfigurator('sc_configure')
;
$container->
    register('foo.baz', '%baz_class%')->
    setFactoryClass('%baz_class%')->
    setFactoryMethod('getInstance')->
    setConfigurator(array('%baz_class%', 'configureStatic1'))
;
$container->
    register('factory_service', 'Bar')->
    setFactoryService('foo.baz')->
    setFactoryMethod('getInstance')
;
$container
    ->register('foo_bar', '%foo_class%')
    ->setScope('prototype')
;
$container->getParameterBag()->clear();
$container->getParameterBag()->add(array(
    'foo_class' => 'Bar\FooClass',
    'baz_class' => 'BazClass',
    'foo' => 'bar',
));

return $container;
