<?php

require_once __DIR__.'/../includes/classes.php';
require_once __DIR__.'/../includes/foo.php';

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\ExpressionLanguage\Expression;

$container = new ContainerBuilder();
$container
    ->register('foo', '\Bar\FooClass')
    ->addTag('foo', array('foo' => 'foo'))
    ->addTag('foo', array('bar' => 'bar', 'baz' => 'baz'))
    ->setFactory(array('Bar\\FooClass', 'getInstance'))
    ->setArguments(array('foo', new Reference('foo.baz'), array('%foo%' => 'foo is %foo%', 'foobar' => '%foo%'), true, new Reference('service_container')))
    ->setProperties(array('foo' => 'bar', 'moo' => new Reference('foo.baz'), 'qux' => array('%foo%' => 'foo is %foo%', 'foobar' => '%foo%')))
    ->addMethodCall('setBar', array(new Reference('bar')))
    ->addMethodCall('initialize')
    ->setConfigurator('sc_configure')
;
$container
    ->register('foo.baz', '%baz_class%')
    ->setFactory(array('%baz_class%', 'getInstance'))
    ->setConfigurator(array('%baz_class%', 'configureStatic1'))
;
$container
    ->register('bar', 'Bar\FooClass')
    ->setArguments(array('foo', new Reference('foo.baz'), new Parameter('foo_bar')))
    ->setConfigurator(array(new Reference('foo.baz'), 'configure'))
;
$container
    ->register('foo_bar', '%foo_class%')
    ->setShared(false)
;
$container->getParameterBag()->clear();
$container->getParameterBag()->add(array(
    'baz_class' => 'BazClass',
    'foo_class' => 'Bar\FooClass',
    'foo' => 'bar',
));
$container->setAlias('alias_for_foo', 'foo');
$container->setAlias('alias_for_alias', 'alias_for_foo');
$container
    ->register('method_call1', 'Bar\FooClass')
    ->setFile(realpath(__DIR__.'/../includes/foo.php'))
    ->addMethodCall('setBar', array(new Reference('foo')))
    ->addMethodCall('setBar', array(new Reference('foo2', ContainerInterface::NULL_ON_INVALID_REFERENCE)))
    ->addMethodCall('setBar', array(new Reference('foo3', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
    ->addMethodCall('setBar', array(new Reference('foobaz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
    ->addMethodCall('setBar', array(new Expression('service("foo").foo() ~ (container.hasParameter("foo") ? parameter("foo") : "default")')))
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
;
$container
    ->register('configurator_service', 'ConfClass')
    ->setPublic(false)
    ->addMethodCall('setFoo', array(new Reference('baz')))
;
$container
    ->register('configured_service', 'stdClass')
    ->setConfigurator(array(new Reference('configurator_service'), 'configureStdClass'))
;
$container
    ->register('configurator_service_simple', 'ConfClass')
    ->addArgument('bar')
    ->setPublic(false)
;
$container
    ->register('configured_service_simple', 'stdClass')
    ->setConfigurator(array(new Reference('configurator_service_simple'), 'configureStdClass'))
;
$container
    ->register('decorated', 'stdClass')
;
$container
    ->register('decorator_service', 'stdClass')
    ->setDecoratedService('decorated')
;
$container
    ->register('decorator_service_with_name', 'stdClass')
    ->setDecoratedService('decorated', 'decorated.pif-pouf')
;
$container
    ->register('deprecated_service', 'stdClass')
    ->setDeprecated(true)
;
$container
    ->register('new_factory', 'FactoryClass')
    ->setProperty('foo', 'bar')
    ->setPublic(false)
;
$container
    ->register('factory_service', 'Bar')
    ->setFactory(array(new Reference('foo.baz'), 'getInstance'))
;
$container
    ->register('new_factory_service', 'FooBarBaz')
    ->setProperty('foo', 'bar')
    ->setFactory(array(new Reference('new_factory'), 'getInstance'))
;
$container
    ->register('service_from_static_method', 'Bar\FooClass')
    ->setFactory(array('Bar\FooClass', 'getInstance'))
;
$container
    ->register('factory_simple', 'SimpleFactoryClass')
    ->addArgument('foo')
    ->setPublic(false)
;
$container
    ->register('factory_service_simple', 'Bar')
    ->setFactory(array(new Reference('factory_simple'), 'getInstance'))
;
$container
    ->register('lazy_context', 'LazyContext')
    ->setArguments(array(new IteratorArgument(array('k1' => new Reference('foo.baz'), 'k2' => new Reference('service_container'))), new IteratorArgument(array())))
;
$container
    ->register('lazy_context_ignore_invalid_ref', 'LazyContext')
    ->setArguments(array(new IteratorArgument(array(new Reference('foo.baz'), new Reference('invalid', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))), new IteratorArgument(array())))
;

return $container;
