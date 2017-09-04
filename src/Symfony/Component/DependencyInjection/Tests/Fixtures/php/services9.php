<?php

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * ProjectServiceContainer.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = array();

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(new ParameterBag($this->getDefaultParameters()));
        $this->normalizedIds = array(
            'psr\\container\\containerinterface' => 'Psr\\Container\\ContainerInterface',
            'symfony\\component\\dependencyinjection\\containerinterface' => 'Symfony\\Component\\DependencyInjection\\ContainerInterface',
        );
        $this->methodMap = array(
            'bar' => 'getBarService',
            'baz' => 'getBazService',
            'configurator_service' => 'getConfiguratorServiceService',
            'configurator_service_simple' => 'getConfiguratorServiceSimpleService',
            'configured_service' => 'getConfiguredServiceService',
            'configured_service_simple' => 'getConfiguredServiceSimpleService',
            'decorated' => 'getDecoratedService',
            'decorator_service' => 'getDecoratorServiceService',
            'decorator_service_with_name' => 'getDecoratorServiceWithNameService',
            'deprecated_service' => 'getDeprecatedServiceService',
            'factory_service' => 'getFactoryServiceService',
            'factory_service_simple' => 'getFactoryServiceSimpleService',
            'factory_simple' => 'getFactorySimpleService',
            'foo' => 'getFooService',
            'foo.baz' => 'getFoo_BazService',
            'foo_bar' => 'getFooBarService',
            'foo_with_inline' => 'getFooWithInlineService',
            'inlined' => 'getInlinedService',
            'lazy_context' => 'getLazyContextService',
            'lazy_context_ignore_invalid_ref' => 'getLazyContextIgnoreInvalidRefService',
            'method_call1' => 'getMethodCall1Service',
            'new_factory' => 'getNewFactoryService',
            'new_factory_service' => 'getNewFactoryServiceService',
            'service_from_static_method' => 'getServiceFromStaticMethodService',
        );
        $this->privates = array(
            'configurator_service' => true,
            'configurator_service_simple' => true,
            'factory_simple' => true,
            'inlined' => true,
            'new_factory' => true,
        );
        $this->aliases = array(
            'Psr\\Container\\ContainerInterface' => 'service_container',
            'Symfony\\Component\\DependencyInjection\\ContainerInterface' => 'service_container',
            'alias_for_alias' => 'foo',
            'alias_for_foo' => 'foo',
        );
    }

    /**
     * Gets the public 'bar' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getBarService()
    {
        $a = ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'};

        $this->services['bar'] = $instance = new \Bar\FooClass('foo', $a, $this->getParameter('foo_bar'));

        $a->configure($instance);

        return $instance;
    }

    /**
     * Gets the public 'baz' shared service.
     *
     * @return \Baz
     */
    protected function getBazService()
    {
        $this->services['baz'] = $instance = new \Baz();

        $instance->setFoo(${($_ = isset($this->services['foo_with_inline']) ? $this->services['foo_with_inline'] : $this->get('foo_with_inline')) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the public 'configured_service' shared service.
     *
     * @return \stdClass
     */
    protected function getConfiguredServiceService()
    {
        $this->services['configured_service'] = $instance = new \stdClass();

        ${($_ = isset($this->services['configurator_service']) ? $this->services['configurator_service'] : $this->getConfiguratorServiceService()) && false ?: '_'}->configureStdClass($instance);

        return $instance;
    }

    /**
     * Gets the public 'configured_service_simple' shared service.
     *
     * @return \stdClass
     */
    protected function getConfiguredServiceSimpleService()
    {
        $this->services['configured_service_simple'] = $instance = new \stdClass();

        ${($_ = isset($this->services['configurator_service_simple']) ? $this->services['configurator_service_simple'] : $this->getConfiguratorServiceSimpleService()) && false ?: '_'}->configureStdClass($instance);

        return $instance;
    }

    /**
     * Gets the public 'decorated' shared service.
     *
     * @return \stdClass
     */
    protected function getDecoratedService()
    {
        return $this->services['decorated'] = new \stdClass();
    }

    /**
     * Gets the public 'decorator_service' shared service.
     *
     * @return \stdClass
     */
    protected function getDecoratorServiceService()
    {
        return $this->services['decorator_service'] = new \stdClass();
    }

    /**
     * Gets the public 'decorator_service_with_name' shared service.
     *
     * @return \stdClass
     */
    protected function getDecoratorServiceWithNameService()
    {
        return $this->services['decorator_service_with_name'] = new \stdClass();
    }

    /**
     * Gets the public 'deprecated_service' shared service.
     *
     * @return \stdClass
     *
     * @deprecated The "deprecated_service" service is deprecated. You should stop using it, as it will soon be removed.
     */
    protected function getDeprecatedServiceService()
    {
        @trigger_error('The "deprecated_service" service is deprecated. You should stop using it, as it will soon be removed.', E_USER_DEPRECATED);

        return $this->services['deprecated_service'] = new \stdClass();
    }

    /**
     * Gets the public 'factory_service' shared service.
     *
     * @return \Bar
     */
    protected function getFactoryServiceService()
    {
        return $this->services['factory_service'] = ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'}->getInstance();
    }

    /**
     * Gets the public 'factory_service_simple' shared service.
     *
     * @return \Bar
     */
    protected function getFactoryServiceSimpleService()
    {
        return $this->services['factory_service_simple'] = ${($_ = isset($this->services['factory_simple']) ? $this->services['factory_simple'] : $this->getFactorySimpleService()) && false ?: '_'}->getInstance();
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getFooService()
    {
        $a = ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'};

        $this->services['foo'] = $instance = \Bar\FooClass::getInstance('foo', $a, array($this->getParameter('foo') => 'foo is '.$this->getParameter('foo').'', 'foobar' => $this->getParameter('foo')), true, $this);

        $instance->foo = 'bar';
        $instance->moo = $a;
        $instance->qux = array($this->getParameter('foo') => 'foo is '.$this->getParameter('foo').'', 'foobar' => $this->getParameter('foo'));
        $instance->setBar(${($_ = isset($this->services['bar']) ? $this->services['bar'] : $this->get('bar')) && false ?: '_'});
        $instance->initialize();
        sc_configure($instance);

        return $instance;
    }

    /**
     * Gets the public 'foo.baz' shared service.
     *
     * @return object A %baz_class% instance
     */
    protected function getFoo_BazService()
    {
        $this->services['foo.baz'] = $instance = call_user_func(array($this->getParameter('baz_class'), 'getInstance'));

        call_user_func(array($this->getParameter('baz_class'), 'configureStatic1'), $instance);

        return $instance;
    }

    /**
     * Gets the public 'foo_bar' service.
     *
     * @return object A %foo_class% instance
     */
    protected function getFooBarService()
    {
        $class = $this->getParameter('foo_class');

        return new $class();
    }

    /**
     * Gets the public 'foo_with_inline' shared service.
     *
     * @return \Foo
     */
    protected function getFooWithInlineService()
    {
        $this->services['foo_with_inline'] = $instance = new \Foo();

        $instance->setBar(${($_ = isset($this->services['inlined']) ? $this->services['inlined'] : $this->getInlinedService()) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the public 'lazy_context' shared service.
     *
     * @return \LazyContext
     */
    protected function getLazyContextService()
    {
        return $this->services['lazy_context'] = new \LazyContext(new RewindableGenerator(function () {
            yield 'k1' => ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'};
            yield 'k2' => $this;
        }, 2), new RewindableGenerator(function () {
            return new \EmptyIterator();
        }, 0));
    }

    /**
     * Gets the public 'lazy_context_ignore_invalid_ref' shared service.
     *
     * @return \LazyContext
     */
    protected function getLazyContextIgnoreInvalidRefService()
    {
        return $this->services['lazy_context_ignore_invalid_ref'] = new \LazyContext(new RewindableGenerator(function () {
            yield 0 => ${($_ = isset($this->services['foo.baz']) ? $this->services['foo.baz'] : $this->get('foo.baz')) && false ?: '_'};
            if ($this->has('invalid')) {
                yield 1 => ${($_ = isset($this->services['invalid']) ? $this->services['invalid'] : $this->get('invalid', ContainerInterface::NULL_ON_INVALID_REFERENCE)) && false ?: '_'};
            }
        }, function () {
            return 1 + (int) ($this->has('invalid'));
        }), new RewindableGenerator(function () {
            return new \EmptyIterator();
        }, 0));
    }

    /**
     * Gets the public 'method_call1' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getMethodCall1Service()
    {
        require_once '%path%foo.php';

        $this->services['method_call1'] = $instance = new \Bar\FooClass();

        $instance->setBar(${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'});
        $instance->setBar(${($_ = isset($this->services['foo2']) ? $this->services['foo2'] : $this->get('foo2', ContainerInterface::NULL_ON_INVALID_REFERENCE)) && false ?: '_'});
        if ($this->has('foo3')) {
            $instance->setBar(${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : $this->get('foo3', ContainerInterface::NULL_ON_INVALID_REFERENCE)) && false ?: '_'});
        }
        if ($this->has('foobaz')) {
            $instance->setBar(${($_ = isset($this->services['foobaz']) ? $this->services['foobaz'] : $this->get('foobaz', ContainerInterface::NULL_ON_INVALID_REFERENCE)) && false ?: '_'});
        }
        $instance->setBar((${($_ = isset($this->services['foo']) ? $this->services['foo'] : $this->get('foo')) && false ?: '_'}->foo() . (($this->hasParameter("foo")) ? ($this->getParameter("foo")) : ("default"))));

        return $instance;
    }

    /**
     * Gets the public 'new_factory_service' shared service.
     *
     * @return \FooBarBaz
     */
    protected function getNewFactoryServiceService()
    {
        $this->services['new_factory_service'] = $instance = ${($_ = isset($this->services['new_factory']) ? $this->services['new_factory'] : $this->getNewFactoryService()) && false ?: '_'}->getInstance();

        $instance->foo = 'bar';

        return $instance;
    }

    /**
     * Gets the public 'service_from_static_method' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getServiceFromStaticMethodService()
    {
        return $this->services['service_from_static_method'] = \Bar\FooClass::getInstance();
    }

    /**
     * Gets the private 'configurator_service' shared service.
     *
     * @return \ConfClass
     */
    protected function getConfiguratorServiceService()
    {
        $this->services['configurator_service'] = $instance = new \ConfClass();

        $instance->setFoo(${($_ = isset($this->services['baz']) ? $this->services['baz'] : $this->get('baz')) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the private 'configurator_service_simple' shared service.
     *
     * @return \ConfClass
     */
    protected function getConfiguratorServiceSimpleService()
    {
        return $this->services['configurator_service_simple'] = new \ConfClass('bar');
    }

    /**
     * Gets the private 'factory_simple' shared service.
     *
     * @return \SimpleFactoryClass
     */
    protected function getFactorySimpleService()
    {
        return $this->services['factory_simple'] = new \SimpleFactoryClass('foo');
    }

    /**
     * Gets the private 'inlined' shared service.
     *
     * @return \Bar
     */
    protected function getInlinedService()
    {
        $this->services['inlined'] = $instance = new \Bar();

        $instance->pub = 'pub';
        $instance->setBaz(${($_ = isset($this->services['baz']) ? $this->services['baz'] : $this->get('baz')) && false ?: '_'});

        return $instance;
    }

    /**
     * Gets the private 'new_factory' shared service.
     *
     * @return \FactoryClass
     */
    protected function getNewFactoryService()
    {
        $this->services['new_factory'] = $instance = new \FactoryClass();

        $instance->foo = 'bar';

        return $instance;
    }

    /**
     * Gets the default parameters.
     *
     * @return array An array of the default parameters
     */
    protected function getDefaultParameters()
    {
        return array(
            'baz_class' => 'BazClass',
            'foo_class' => 'Bar\\FooClass',
            'foo' => 'bar',
        );
    }
}
