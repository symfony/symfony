<?php

require_once __DIR__.'/../includes/classes.php';
require_once __DIR__.'/../includes/foo.php';

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
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
    ->setPublic(true)
;
$container
    ->register('foo.baz', '%baz_class%')
    ->setFactory(array('%baz_class%', 'getInstance'))
    ->setConfigurator(array('%baz_class%', 'configureStatic1'))
    ->setPublic(true)
;
$container
    ->register('bar', 'Bar\FooClass')
    ->setArguments(array('foo', new Reference('foo.baz'), new Parameter('foo_bar')))
    ->setConfigurator(array(new Reference('foo.baz'), 'configure'))
    ->setPublic(true)
;
$container
    ->register('foo_bar', '%foo_class%')
    ->addArgument(new Reference('deprecated_service'))
    ->setShared(false)
    ->setPublic(true)
;
$container->getParameterBag()->clear();
$container->getParameterBag()->add(array(
    'baz_class' => 'BazClass',
    'foo_class' => 'Bar\FooClass',
    'foo' => 'bar',
));
$container
    ->register('method_call1', 'Bar\FooClass')
    ->setFile(realpath(__DIR__.'/../includes/foo.php'))
    ->addMethodCall('setBar', array(new Reference('foo')))
    ->addMethodCall('setBar', array(new Reference('foo2', ContainerInterface::NULL_ON_INVALID_REFERENCE)))
    ->addMethodCall('setBar', array(new Reference('foo3', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
    ->addMethodCall('setBar', array(new Reference('foobaz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)))
    ->addMethodCall('setBar', array(new Expression('service("foo").foo() ~ (container.hasParameter("foo") ? parameter("foo") : "default")')))
    ->setPublic(true)
;
$container
    ->register('foo_with_inline', 'Foo')
    ->addMethodCall('setBar', array(new Reference('inlined')))
    ->setPublic(true)
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
    ->setPublic(true)
;
$container
    ->register('request', 'Request')
    ->setSynthetic(true)
    ->setPublic(true)
;
$container
    ->register('configurator_service', 'ConfClass')
    ->setPublic(false)
    ->addMethodCall('setFoo', array(new Reference('baz')))
;
$container
    ->register('configured_service', 'stdClass')
    ->setConfigurator(array(new Reference('configurator_service'), 'configureStdClass'))
    ->setPublic(true)
;
$container
    ->register('configurator_service_simple', 'ConfClass')
    ->addArgument('bar')
    ->setPublic(false)
;
$container
    ->register('configured_service_simple', 'stdClass')
    ->setConfigurator(array(new Reference('configurator_service_simple'), 'configureStdClass'))
    ->setPublic(true)
;
$container
    ->register('decorated', 'stdClass')
    ->setPublic(true)
;
$container
    ->register('decorator_service', 'stdClass')
    ->setDecoratedService('decorated')
    ->setPublic(true)
;
$container
    ->register('decorator_service_with_name', 'stdClass')
    ->setDecoratedService('decorated', 'decorated.pif-pouf')
    ->setPublic(true)
;
$container
    ->register('deprecated_service', 'stdClass')
    ->setDeprecated(true)
    ->setPublic(true)
;
$container
    ->register('new_factory', 'FactoryClass')
    ->setProperty('foo', 'bar')
    ->setPublic(false)
;
$container
    ->register('factory_service', 'Bar')
    ->setFactory(array(new Reference('foo.baz'), 'getInstance'))
    ->setPublic(true)
;
$container
    ->register('new_factory_service', 'FooBarBaz')
    ->setProperty('foo', 'bar')
    ->setFactory(array(new Reference('new_factory'), 'getInstance'))
    ->setPublic(true)
;
$container
    ->register('service_from_static_method', 'Bar\FooClass')
    ->setFactory(array('Bar\FooClass', 'getInstance'))
    ->setPublic(true)
;
$container
    ->register('factory_simple', 'SimpleFactoryClass')
    ->addArgument('foo')
    ->setDeprecated(true)
    ->setPublic(false)
;
$container
    ->register('factory_service_simple', 'Bar')
    ->setFactory(array(new Reference('factory_simple'), 'getInstance'))
    ->setPublic(true)
;
$container
    ->register('lazy_context', 'LazyContext')
    ->setArguments(array(new IteratorArgument(array('k1' => new Reference('foo.baz'), 'k2' => new Reference('service_container'))), new IteratorArgument(array())))
    ->setPublic(true)
;
$container
    ->register('lazy_context_ignore_invalid_ref', 'LazyContext')
    ->setArguments(array(new IteratorArgument(array(new Reference('foo.baz'), new Reference('invalid', ContainerInterface::IGNORE_ON_INVALID_REFERENCE))), new IteratorArgument(array())))
    ->setPublic(true)
;
$container
    ->register('BAR', 'stdClass')
    ->setProperty('bar', new Reference('bar'))
    ->setPublic(true)
;
$container->register('bar2', 'stdClass')->setPublic(true);
$container->register('BAR2', 'stdClass')->setPublic(true);
$container
    ->register('tagged_iterator_foo', 'Bar')
    ->addTag('foo')
    ->setPublic(false)
;
$container
    ->register('tagged_iterator', 'Bar')
    ->addArgument(new TaggedIteratorArgument('foo'))
    ->setPublic(true)
;
$container->setAlias('alias_for_foo', 'foo')->setPublic(true);
$container->setAlias('alias_for_alias', 'alias_for_foo')->setPublic(true);

return $container;
