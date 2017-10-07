<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Bar\FooClass;
use Symfony\Component\DependencyInjection\Parameter;

require_once __DIR__.'/../includes/classes.php';
require_once __DIR__.'/../includes/foo.php';

return function (ContainerConfigurator $c) {
    $p = $c->parameters();
    $p->set('baz_class', 'BazClass');
    $p->set('foo_class', FooClass::class)
      ->set('foo', 'bar');

    $s = $c->services();
    $s->set('foo')
        ->args(array('foo', ref('foo.baz'), array('%foo%' => 'foo is %foo%', 'foobar' => '%foo%'), true, ref('service_container')))
        ->class(FooClass::class)
        ->tag('foo', array('foo' => 'foo'))
        ->tag('foo', array('bar' => 'bar', 'baz' => 'baz'))
        ->factory(array(FooClass::class, 'getInstance'))
        ->property('foo', 'bar')
        ->property('moo', ref('foo.baz'))
        ->property('qux', array('%foo%' => 'foo is %foo%', 'foobar' => '%foo%'))
        ->call('setBar', array(ref('bar')))
        ->call('initialize')
        ->configurator('sc_configure');

    $s->set('foo.baz', '%baz_class%')
        ->factory(array('%baz_class%', 'getInstance'))
        ->configurator(array('%baz_class%', 'configureStatic1'));

    $s->set('bar', FooClass::class)
        ->args(array('foo', ref('foo.baz'), new Parameter('foo_bar')))
        ->configurator(array(ref('foo.baz'), 'configure'));

    $s->set('foo_bar', '%foo_class%')
        ->args(array(ref('deprecated_service')))
        ->share(false);

    $s->set('method_call1', 'Bar\FooClass')
        ->file(realpath(__DIR__.'/../includes/foo.php'))
        ->call('setBar', array(ref('foo')))
        ->call('setBar', array(ref('foo2')->nullOnInvalid()))
        ->call('setBar', array(ref('foo3')->ignoreOnInvalid()))
        ->call('setBar', array(ref('foobaz')->ignoreOnInvalid()))
        ->call('setBar', array(expr('service("foo").foo() ~ (container.hasParameter("foo") ? parameter("foo") : "default")')));

    $s->set('foo_with_inline', 'Foo')
        ->call('setBar', array(ref('inlined')));

    $s->set('inlined', 'Bar')
        ->property('pub', 'pub')
        ->call('setBaz', array(ref('baz')))
        ->private();

    $s->set('baz', 'Baz')
        ->call('setFoo', array(ref('foo_with_inline')));

    $s->set('request', 'Request')
        ->synthetic();

    $s->set('configurator_service', 'ConfClass')
        ->private()
        ->call('setFoo', array(ref('baz')));

    $s->set('configured_service', 'stdClass')
        ->configurator(array(ref('configurator_service'), 'configureStdClass'));

    $s->set('configurator_service_simple', 'ConfClass')
        ->args(array('bar'))
        ->private();

    $s->set('configured_service_simple', 'stdClass')
        ->configurator(array(ref('configurator_service_simple'), 'configureStdClass'));

    $s->set('decorated', 'stdClass');

    $s->set('decorator_service', 'stdClass')
        ->decorate('decorated');

    $s->set('decorator_service_with_name', 'stdClass')
        ->decorate('decorated', 'decorated.pif-pouf');

    $s->set('deprecated_service', 'stdClass')
        ->deprecate();

    $s->set('new_factory', 'FactoryClass')
        ->property('foo', 'bar')
        ->private();

    $s->set('factory_service', 'Bar')
        ->factory(array(ref('foo.baz'), 'getInstance'));

    $s->set('new_factory_service', 'FooBarBaz')
        ->property('foo', 'bar')
        ->factory(array(ref('new_factory'), 'getInstance'));

    $s->set('service_from_static_method', 'Bar\FooClass')
        ->factory(array('Bar\FooClass', 'getInstance'));

    $s->set('factory_simple', 'SimpleFactoryClass')
        ->deprecate()
        ->args(array('foo'))
        ->private();

    $s->set('factory_service_simple', 'Bar')
        ->factory(array(ref('factory_simple'), 'getInstance'));

    $s->set('lazy_context', 'LazyContext')
        ->args(array(iterator(array('k1' => ref('foo.baz'), 'k2' => ref('service_container'))), iterator(array())));

    $s->set('lazy_context_ignore_invalid_ref', 'LazyContext')
        ->args(array(iterator(array(ref('foo.baz'), ref('invalid')->ignoreOnInvalid())), iterator(array())));

    $s->set('tagged_iterator_foo', 'Bar')
        ->private()
        ->tag('foo');

    $s->set('tagged_iterator', 'Bar')
        ->public()
        ->args(array(tagged('foo')));

    $s->alias('alias_for_foo', 'foo')->private()->public();
    $s->alias('alias_for_alias', ref('alias_for_foo'));
};
