<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Bar\FooClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

require_once __DIR__.'/../includes/classes.php';
require_once __DIR__.'/../includes/foo.php';

return function (ContainerConfigurator $c) {
    $p = $c->parameters();
    $p->set('baz_class', 'BazClass');
    $p->set('foo_class', FooClass::class)
      ->set('foo', 'bar');

    $s = $c->services()->defaults()->public();
    $s->set('foo')
        ->args(['foo', service('foo.baz'), ['%foo%' => 'foo is %foo%', 'foobar' => '%foo%'], true, service('service_container')])
        ->class(FooClass::class)
        ->tag('foo', ['foo' => 'foo'])
        ->tag('foo', ['bar' => 'bar', 'baz' => 'baz'])
        ->tag('foo', ['name' => 'bar', 'baz' => 'baz'])
        ->factory([FooClass::class, 'getInstance'])
        ->property('foo', 'bar')
        ->property('moo', service('foo.baz'))
        ->property('qux', ['%foo%' => 'foo is %foo%', 'foobar' => '%foo%'])
        ->call('setBar', [service('bar')])
        ->call('initialize')
        ->configurator('sc_configure');

    $s->set('foo.baz', '%baz_class%')
        ->factory(['%baz_class%', 'getInstance'])
        ->configurator(['%baz_class%', 'configureStatic1']);

    $s->set('bar', FooClass::class)
        ->args(['foo', service('foo.baz'), new Parameter('foo_bar')])
        ->configurator([service('foo.baz'), 'configure']);

    $s->set('foo_bar', '%foo_class%')
        ->args([service('deprecated_service')])
        ->share(false);

    $s->set('method_call1', 'Bar\FooClass')
        ->file(realpath(__DIR__.'/../includes/foo.php'))
        ->call('setBar', [service('foo')])
        ->call('setBar', [service('foo2')->nullOnInvalid()])
        ->call('setBar', [service('foo3')->ignoreOnInvalid()])
        ->call('setBar', [service('foobaz')->ignoreOnInvalid()])
        ->call('setBar', [expr('service("foo").foo() ~ (container.hasParameter("foo") ? parameter("foo") : "default")')]);

    $s->set('foo_with_inline', 'Foo')
        ->call('setBar', [service('inlined')]);

    $s->set('inlined', 'Bar')
        ->property('pub', 'pub')
        ->call('setBaz', [service('baz')])
        ->private();

    $s->set('baz', 'Baz')
        ->call('setFoo', [service('foo_with_inline')]);

    $s->set('request', 'Request')
        ->synthetic();

    $s->set('configurator_service', 'ConfClass')
        ->private()
        ->call('setFoo', [service('baz')]);

    $s->set('configured_service', 'stdClass')
        ->configurator([service('configurator_service'), 'configureStdClass']);

    $s->set('configurator_service_simple', 'ConfClass')
        ->args(['bar'])
        ->private();

    $s->set('configured_service_simple', 'stdClass')
        ->configurator([service('configurator_service_simple'), 'configureStdClass']);

    $s->set('decorated', 'stdClass');

    $s->set('decorator_service', 'stdClass')
        ->decorate('decorated');

    $s->set('decorator_service_with_name', 'stdClass')
        ->decorate('decorated', 'decorated.pif-pouf');

    $s->set('deprecated_service', 'stdClass')
        ->deprecate('vendor/package', '1.1', 'The "%service_id%" service is deprecated. You should stop using it, as it will be removed in the future.');

    $s->set('new_factory', 'FactoryClass')
        ->property('foo', 'bar')
        ->private();

    $s->set('factory_service', 'Bar')
        ->factory([service('foo.baz'), 'getInstance']);

    $s->set('new_factory_service', 'FooBarBaz')
        ->property('foo', 'bar')
        ->factory([service('new_factory'), 'getInstance']);

    $s->set('service_from_static_method', 'Bar\FooClass')
        ->factory(['Bar\FooClass', 'getInstance']);

    $s->set('factory_simple', 'SimpleFactoryClass')
        ->deprecate('vendor/package', '1.1', 'The "%service_id%" service is deprecated. You should stop using it, as it will be removed in the future.')
        ->args(['foo'])
        ->private();

    $s->set('factory_service_simple', 'Bar')
        ->factory([service('factory_simple'), 'getInstance']);

    $s->set('lazy_context', 'LazyContext')
        ->args([iterator(['k1' => service('foo.baz'), 'k2' => service('service_container')]), iterator([])]);

    $s->set('lazy_context_ignore_invalid_ref', 'LazyContext')
        ->args([iterator([service('foo.baz'), service('invalid')->ignoreOnInvalid()]), iterator([])]);

    $s->set('BAR', 'stdClass')->property('bar', service('bar'));
    $s->set('bar2', 'stdClass');
    $s->set('BAR2', 'stdClass');

    $s->set('tagged_iterator_foo', 'Bar')
        ->private()
        ->tag('foo');

    $s->set('tagged_iterator', 'Bar')
        ->args([tagged_iterator('foo')]);

    $s->set('runtime_error', 'stdClass')
        ->args([new Reference('errored_definition', ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)]);
    $s->set('errored_definition', 'stdClass')->private();
    $s->set('preload_sidekick', 'stdClass')
        ->tag('container.preload', ['class' => 'Some\Sidekick1'])
        ->tag('container.preload', ['class' => 'Some\Sidekick2'])
        ->public();

    $s->set('a_factory', 'Bar')
        ->private();
    $s->set('a_service', 'Bar')
        ->factory([service('a_factory'), 'getBar']);
    $s->set('b_service', 'Bar')
        ->factory([service('a_factory'), 'getBar']);

    $s->alias('alias_for_foo', 'foo')->private()->public();
    $s->alias('alias_for_alias', service('alias_for_foo'));
};
