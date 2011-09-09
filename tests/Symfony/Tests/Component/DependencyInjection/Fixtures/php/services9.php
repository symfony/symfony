<?php

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\InactiveScopeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * ProjectServiceContainer
 *
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 */
class ProjectServiceContainer extends Container
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(new ParameterBag($this->getDefaultParameters()));
    }

    /**
     * Gets the 'bar' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return FooClass A FooClass instance.
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new \FooClass('foo', $this->get('foo.baz'), $this->getParameter('foo_bar'));

        $this->get('foo.baz')->configure($instance);

        return $instance;
    }

    /**
     * Gets the 'factory_service' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return Object An instance returned by foo.baz::getInstance().
     */
    protected function getFactoryServiceService()
    {
        return $this->services['factory_service'] = $this->get('foo.baz')->getInstance();
    }

    /**
     * Gets the 'foo' service.
     *
     * @return FooClass A FooClass instance.
     */
    protected function getFooService()
    {
        $a = $this->get('foo.baz');

        $instance = call_user_func(array('FooClass', 'getInstance'), 'foo', $a, array($this->getParameter('foo') => 'foo is '.$this->getParameter('foo'), 'bar' => $this->getParameter('foo')), true, $this);

        $instance->setBar($this->get('bar'));
        $instance->initialize();
        $instance->foo = 'bar';
        $instance->moo = $a;
        sc_configure($instance);

        return $instance;
    }

    /**
     * Gets the 'foo.baz' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return Object A %baz_class% instance.
     */
    protected function getFoo_BazService()
    {
        $this->services['foo.baz'] = $instance = call_user_func(array($this->getParameter('baz_class'), 'getInstance'));

        call_user_func(array($this->getParameter('baz_class'), 'configureStatic1'), $instance);

        return $instance;
    }

    /**
     * Gets the 'foo_bar' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return Object A %foo_class% instance.
     */
    protected function getFooBarService()
    {
        $class = $this->getParameter('foo_class');
        return $this->services['foo_bar'] = new $class();
    }

    /**
     * Gets the 'method_call1' service.
     *
     * This service is shared.
     * This method always returns the same instance of the service.
     *
     * @return FooClass A FooClass instance.
     */
    protected function getMethodCall1Service()
    {
        require_once '%path%foo.php';

        $this->services['method_call1'] = $instance = new \FooClass();

        $instance->setBar($this->get('foo'));
        $instance->setBar($this->get('foo2', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        if ($this->has('foo3')) {
            $instance->setBar($this->get('foo3', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }
        if ($this->has('foobaz')) {
            $instance->setBar($this->get('foobaz', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }

        return $instance;
    }

    /**
     * Gets the alias_for_foo service alias.
     *
     * @return FooClass An instance of the foo service
     */
    protected function getAliasForFooService()
    {
        return $this->get('foo');
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
            'foo_class' => 'FooClass',
            'foo' => 'bar',
        );
    }
}
