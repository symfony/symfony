<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\Parameter;

$container = new Builder();
$container->
    register('foo', 'FooClass')->
    addAnnotation('foo', array('foo' => 'foo'))->
    addAnnotation('foo', array('bar' => 'bar'))->
    setConstructor('getInstance')->
    setArguments(array('foo', new Reference('foo.baz'), array('%foo%' => 'foo is %foo%', 'bar' => '%foo%'), true, new Reference('service_container')))->
    setFile(realpath(__DIR__.'/../includes/foo.php'))->
    setShared(false)->
    addMethodCall('setBar', array('bar'))->
    addMethodCall('initialize')->
    setConfigurator('sc_configure')
;
$container->
    register('bar', 'FooClass')->
    setArguments(array('foo', new Reference('foo.baz'), new Parameter('foo_bar')))->
    setShared(true)->
    setConfigurator(array(new Reference('foo.baz'), 'configure'))
;
$container->
    register('foo.baz', '%baz_class%')->
    setConstructor('getInstance')->
    setConfigurator(array('%baz_class%', 'configureStatic1'))
;
$container->register('foo_bar', '%foo_class%');
$container->getParameterBag()->clear();
$container->getParameterBag()->add(array(
    'baz_class' => 'BazClass',
    'foo_class' => 'FooClass',
    'foo' => 'bar',
));
$container->setAlias('alias_for_foo', 'foo');
$container->
    register('method_call1', 'FooClass')->
    addMethodCall('setBar', array(new Reference('foo')))->
    addMethodCall('setBar', array(new Reference('foo', ContainerInterface::NULL_ON_INVALID_REFERENCE)))->
    addMethodCall('setBar', array(new Reference('foo', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))->
    addMethodCall('setBar', array(new Reference('foobaz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
;

return $container;
