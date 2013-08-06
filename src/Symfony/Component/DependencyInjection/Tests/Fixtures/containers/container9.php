<?php

require_once __DIR__.'/../includes/classes.php';

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

$container = new ContainerBuilder();
$container->
    register('foo', 'FooClass')->
    addTag('foo', array('foo' => 'foo'))->
    addTag('foo', array('bar' => 'bar'))->
    setFactoryClass('FooClass')->
    setFactoryMethod('getInstance')->
    setArguments(array('foo', new Reference('foo.baz'), array('%foo%' => 'foo is %foo%', 'foobar' => '%foo%'), true, new Reference('service_container')))->
    setProperties(array('foo' => 'bar', 'moo' => new Reference('foo.baz')))->
    addMethodCall('setBar', array(new Reference('bar')))->
    addMethodCall('initialize')->
    setConfigurator('sc_configure')
;
$container->
    register('bar', 'FooClass')->
    setArguments(array('foo', new Reference('foo.baz'), new Parameter('foo_bar')))->
    setScope('container')->
    setConfigurator(array(new Reference('foo.baz'), 'configure'))
;
$container->
    register('foo.baz', '%baz_class%')->
    setFactoryClass('%baz_class%')->
    setFactoryMethod('getInstance')->
    setConfigurator(array('%baz_class%', 'configureStatic1'))
;
$container->
    register('foo_bar', '%foo_class%')->
    setScope('prototype')
;
$container->getParameterBag()->clear();
$container->getParameterBag()->add(array(
    'baz_class' => 'BazClass',
    'foo_class' => 'FooClass',
    'foo' => 'bar',
));
$container->setAlias('alias_for_foo', 'foo');
$container->setAlias('alias_for_alias', 'alias_for_foo');
$container->
    register('method_call1', 'FooClass')->
    setFile(realpath(__DIR__.'/../includes/foo.php'))->
    addMethodCall('setBar', array(new Reference('foo')))->
    addMethodCall('setBar', array(new Reference('foo2', ContainerInterface::NULL_ON_INVALID_REFERENCE)))->
    addMethodCall('setBar', array(new Reference('foo3', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))->
    addMethodCall('setBar', array(new Reference('foobaz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
;
$container->
    register('factory_service', 'Bar')->
    setFactoryService('foo.baz')->
    setFactoryMethod('getInstance')
;

$container
    ->register('foo_with_inline', 'Foo')
    ->addMethodCall('setBar', array(new Reference('inlined')))
;
$container
    ->register('inlined', 'Bar')
    ->setProperty('pub', 'pub')
    ->addMethodCall('setBaz', array(new Reference('baz')))
    ->setPublic(false)
;
$container
    ->register('baz', 'Baz')
    ->addMethodCall('setFoo', array(new Reference('foo_with_inline')))
;
$container
    ->register('request', 'Request')
    ->setSynthetic(true)
    ->setSynchronized(true)
;
$container
    ->register('depends_on_request', 'stdClass')
    ->addMethodCall('setRequest', array(new Reference('request', ContainerInterface::NULL_ON_INVALID_REFERENCE, false)))
;

return $container;
