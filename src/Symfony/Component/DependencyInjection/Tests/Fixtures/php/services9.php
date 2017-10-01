<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class ProjectServiceContainer extends Container
{
    private $parameters;
    private $targetDirs = array();

    public function __construct()
    {
        parent::__construct(new ParameterBag($this->getDefaultParameters()));
        $this->methodMap = array(
            'bar' => 'getBarService',
            'baz' => 'getBazService',
            'configurator_service' => 'getConfiguratorServiceService',
            'configured_service' => 'getConfiguredServiceService',
            'decorated' => 'getDecoratedService',
            'decorator_service' => 'getDecoratorServiceService',
            'decorator_service_with_name' => 'getDecoratorServiceWithNameService',
            'deprecated_service' => 'getDeprecatedServiceService',
            'factory_service' => 'getFactoryServiceService',
            'foo' => 'getFooService',
            'foo.baz' => 'getFoo_BazService',
            'foo_bar' => 'getFooBarService',
            'foo_with_inline' => 'getFooWithInlineService',
            'inlined' => 'getInlinedService',
            'method_call1' => 'getMethodCall1Service',
            'new_factory' => 'getNewFactoryService',
            'new_factory_service' => 'getNewFactoryServiceService',
            'request' => 'getRequestService',
            'service_from_static_method' => 'getServiceFromStaticMethodService',
        );
        $this->aliases = array(
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
        $a = $this->get('foo.baz');

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

        $instance->setFoo($this->get('foo_with_inline'));

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

        $this->get('configurator_service')->configureStdClass($instance);

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
        return $this->services['factory_service'] = $this->get('foo.baz')->getInstance();
    }

    /**
     * Gets the public 'foo' shared service.
     *
     * @return \Bar\FooClass
     */
    protected function getFooService()
    {
        $a = $this->get('foo.baz');

        $this->services['foo'] = $instance = \Bar\FooClass::getInstance('foo', $a, array($this->getParameter('foo') => 'foo is '.$this->getParameter('foo').'', 'foobar' => $this->getParameter('foo')), true, $this);

        $instance->foo = 'bar';
        $instance->moo = $a;
        $instance->qux = array($this->getParameter('foo') => 'foo is '.$this->getParameter('foo').'', 'foobar' => $this->getParameter('foo'));
        $instance->setBar($this->get('bar'));
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

        $instance->setBar($this->get('inlined'));

        return $instance;
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

        $instance->setBar($this->get('foo'));
        $instance->setBar($this->get('foo2', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        if ($this->has('foo3')) {
            $instance->setBar($this->get('foo3', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
        if ($this->has('foobaz')) {
            $instance->setBar($this->get('foobaz', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
        $instance->setBar(($this->get("foo")->foo() . (($this->hasParameter("foo")) ? ($this->getParameter("foo")) : ("default"))));

        return $instance;
    }

    /**
     * Gets the public 'new_factory_service' shared service.
     *
     * @return \FooBarBaz
     */
    protected function getNewFactoryServiceService()
    {
        $this->services['new_factory_service'] = $instance = $this->get('new_factory')->getInstance();

        $instance->foo = 'bar';

        return $instance;
    }

    /**
     * Gets the public 'request' shared service.
     *
     * @throws RuntimeException always since this service is expected to be injected dynamically
     */
    protected function getRequestService()
    {
        throw new RuntimeException('You have requested a synthetic service ("request"). The DIC does not know how to construct this service.');
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

        $instance->setFoo($this->get('baz'));

        return $instance;
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
        $instance->setBaz($this->get('baz'));

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
