<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * ProjectServiceContainer.
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
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
        $this->parameters = $this->getDefaultParameters();

        $this->services =
        $this->scopedServices =
        $this->scopeStacks = array();
        $this->scopes = array();
        $this->scopeChildren = array();
        $this->methodMap = array(
            'bar' => 'getBarService',
            'baz' => 'getBazService',
            'configured_service' => 'getConfiguredServiceService',
            'decorator_service' => 'getDecoratorServiceService',
            'decorator_service_with_name' => 'getDecoratorServiceWithNameService',
            'deprecated_service' => 'getDeprecatedServiceService',
            'factory_service' => 'getFactoryServiceService',
            'foo' => 'getFooService',
            'foo.baz' => 'getFoo_BazService',
            'foo_bar' => 'getFooBarService',
            'foo_with_inline' => 'getFooWithInlineService',
            'method_call1' => 'getMethodCall1Service',
            'new_factory_service' => 'getNewFactoryServiceService',
            'request' => 'getRequestService',
            'service_from_static_method' => 'getServiceFromStaticMethodService',
        );
        $this->aliases = array(
            'alias_for_alias' => 'foo',
            'alias_for_foo' => 'foo',
            'decorated' => 'decorator_service_with_name',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped frozen container.');
    }

    /**
     * Gets the 'bar' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Bar\FooClass A Bar\FooClass instance
     */
    protected function getBarService()
    {
        $a = $this->get('foo.baz');

        $this->services['bar'] = $instance = new \Bar\FooClass('foo', $a, $this->getParameter('foo_bar'));

        $a->configure($instance);

        return $instance;
    }

    /**
     * Gets the 'baz' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Baz A Baz instance
     */
    protected function getBazService()
    {
        $this->services['baz'] = $instance = new \Baz();

        $instance->setFoo($this->get('foo_with_inline'));

        return $instance;
    }

    /**
     * Gets the 'configured_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getConfiguredServiceService()
    {
        $a = new \ConfClass();
        $a->setFoo($this->get('baz'));

        $this->services['configured_service'] = $instance = new \stdClass();

        $a->configureStdClass($instance);

        return $instance;
    }

    /**
     * Gets the 'decorator_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getDecoratorServiceService()
    {
        return $this->services['decorator_service'] = new \stdClass();
    }

    /**
     * Gets the 'decorator_service_with_name' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     */
    protected function getDecoratorServiceWithNameService()
    {
        return $this->services['decorator_service_with_name'] = new \stdClass();
    }

    /**
     * Gets the 'deprecated_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \stdClass A stdClass instance
     *
     * @deprecated The "deprecated_service" service is deprecated. You should stop using it, as it will soon be removed.
     */
    protected function getDeprecatedServiceService()
    {
        @trigger_error('The "deprecated_service" service is deprecated. You should stop using it, as it will soon be removed.', E_USER_DEPRECATED);

        return $this->services['deprecated_service'] = new \stdClass();
    }

    /**
     * Gets the 'factory_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Bar A Bar instance
     */
    protected function getFactoryServiceService()
    {
        return $this->services['factory_service'] = $this->get('foo.baz')->getInstance();
    }

    /**
     * Gets the 'foo' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Bar\FooClass A Bar\FooClass instance
     */
    protected function getFooService()
    {
        $a = $this->get('foo.baz');

        $this->services['foo'] = $instance = \Bar\FooClass::getInstance('foo', $a, array('bar' => 'foo is bar', 'foobar' => 'bar'), true, $this);

        $instance->setBar($this->get('bar'));
        $instance->initialize();
        $instance->foo = 'bar';
        $instance->moo = $a;
        $instance->qux = array('bar' => 'foo is bar', 'foobar' => 'bar');
        sc_configure($instance);

        return $instance;
    }

    /**
     * Gets the 'foo.baz' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \BazClass A BazClass instance
     */
    protected function getFoo_BazService()
    {
        $this->services['foo.baz'] = $instance = \BazClass::getInstance();

        \BazClass::configureStatic1($instance);

        return $instance;
    }

    /**
     * Gets the 'foo_bar' service.
     *
     * @return \Bar\FooClass A Bar\FooClass instance
     */
    protected function getFooBarService()
    {
        return new \Bar\FooClass();
    }

    /**
     * Gets the 'foo_with_inline' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Foo A Foo instance
     */
    protected function getFooWithInlineService()
    {
        $a = new \Bar();

        $this->services['foo_with_inline'] = $instance = new \Foo();

        $a->setBaz($this->get('baz'));
        $a->pub = 'pub';

        $instance->setBar($a);

        return $instance;
    }

    /**
     * Gets the 'method_call1' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Bar\FooClass A Bar\FooClass instance
     */
    protected function getMethodCall1Service()
    {
        require_once '%path%foo.php';

        $this->services['method_call1'] = $instance = new \Bar\FooClass();

        $instance->setBar($this->get('foo'));
        $instance->setBar(NULL);
        $instance->setBar(($this->get("foo")->foo() . (($this->hasparameter("foo")) ? ($this->getParameter("foo")) : ("default"))));

        return $instance;
    }

    /**
     * Gets the 'new_factory_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \FooBarBaz A FooBarBaz instance
     */
    protected function getNewFactoryServiceService()
    {
        $a = new \FactoryClass();
        $a->foo = 'bar';

        $this->services['new_factory_service'] = $instance = $a->getInstance();

        $instance->foo = 'bar';

        return $instance;
    }

    /**
     * Gets the 'request' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @throws RuntimeException always since this service is expected to be injected dynamically
     */
    protected function getRequestService()
    {
        throw new RuntimeException('You have requested a synthetic service ("request"). The DIC does not know how to construct this service.');
    }

    /**
     * Gets the 'service_from_static_method' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return \Bar\FooClass A Bar\FooClass instance
     */
    protected function getServiceFromStaticMethodService()
    {
        return $this->services['service_from_static_method'] = \Bar\FooClass::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($name)
    {
        $name = strtolower($name);

        if (!(isset($this->parameters[$name]) || array_key_exists($name, $this->parameters))) {
            throw new InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
        }

        return $this->parameters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter($name)
    {
        $name = strtolower($name);

        return isset($this->parameters[$name]) || array_key_exists($name, $this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($name, $value)
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterBag()
    {
        if (null === $this->parameterBag) {
            $this->parameterBag = new FrozenParameterBag($this->parameters);
        }

        return $this->parameterBag;
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
